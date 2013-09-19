<?php 
	/**
	*
	* @author Phil Gale
	* @desc This class adds rendering functionality to the WordExtractor object
	*
	*/
	class WordRender extends WordExtractor {
		
		/**
		 * @name $_styles
		 * @access private
		 * @static
		 * @var array
		 * @desc A hash array of each style, its html tag and (optionally) a css class eg. ('RedParagraph' => array('tag' => 'p', 'class' => 'redtext'))
		 *  - Pass null as a tag and the element will not be rendered
		 */
		private static $_styles = array(
			# Add styles in here
			# 'exampleHeader' => array('tag' => 'h1'),
		);
		
		/**
		 * @var array $unknownStyles
		 * @desc If any $_styles[] are missing, a 'p' tag is used and the style name is placed inside this array.
		 */
		public $unknownStyles = array();
		
		/**
		 * @name toHtml
		 * @desc Takes $this->parsed and converts the parsed docx file into a full string of html
		 */
		public function toHtml(){
			$htmlArray = array();
			$listItems = array();
			foreach ($this->parsed as $i => $node){
				$html = '';
				switch ($node['type']){
					case 'p':
						# Check if it was parsed as a list, or standard paragraph
						if (strpos($node['text'], '<li>') !== false){
							$html .= $node['text'];
						} else {
							# Titles are found by checking the styles
							if ($node['style'] == '')
								$html .= '<p>' . $node['text'] . '</p>' . PHP_EOL;
							else {
								if (isset(WordRender::$_styles[$node['style']])){
									$styleData = WordRender::$_styles[$node['style']];
									if ($styleData['tag'] == null) continue;
									$classStr = '';
									if (isset($styleData['class'])){
										$classStr = ' class="' . $styleData['class'] . '"';
									}
									
									$html .= '<' . $styleData['tag'] . $classStr . '>' . $node['text'] . '</' . $styleData['tag'] . '>' . PHP_EOL;
								} else {
									$html .= '<p>' . $node['text'] . '</p>' . PHP_EOL;
									$this->unknownStyles[] = $node['style'];
								}
							}
						}
						$htmlArray[$i] = $html;
					break;
					case 'list_item':
						if (!isset($activeIndentation))
							$activeIndentation = $node['indent'];
						else {
							if ($activeIndentation < $node['indent']){
								$indentI = 0;
								for ($loopI = $activeIndentation; $loopI < $node['indent']; $loopI++ ){
									$indentI++;
								}
								if (isset($listItems[$i - 1]))
									$listItems[$i - 1]['open_ul_count'] = $indentI;
							} else {
								$indentI = 0;
								for ($loopI = $activeIndentation; $loopI > $node['indent']; $loopI-- ){
									$indentI++;
								}
								$node['close_ul_count'] = $indentI;
							}
						}
						
						$calcClosingTags = false;
						
						if (isset($this->parsed[$i + 1])){
							if ($this->parsed[$i + 1]['type'] != 'list_item'){
								$calcClosingTags = true;
							}
						} else $calcClosingTags = true;
						
						if ($calcClosingTags){
							if ($node['indent'] != 0){
								$node['lastClosingTags'] = $node['indent'] + 1;
							} else $node['lastClosingTags'] = 1;
						}
						
						$listItems[$i] = $node;
						$activeIndentation = $node['indent'];
					break;
					case 'image':
						$imageInfo = explode(".", $node['name']);
						$html .=  '<img width="' . $node['w'] . '" height="' . $node['h'] . '" title="' . $imageInfo[0] . '" src="data:image/' . $imageInfo[1] . ';base64,' . $node['data'] . '" alt="" />';
						$htmlArray[$i] = $html;
					break;
					case 'table':
						$html .= '<table>';
							foreach ($node['rows'] as $i => $row){
								if ($row['headers'] == true)
									$html .= '<tr class="headers">';
								else 
									$html .= '<tr>';
								
								if (isset($row[$i][0])){
									foreach ($row[$i] as $ii => $cell){
										$colspan = '';
										if (isset($cell['colspan'])){
											if ($cell['colspan'] > 1){
												$colspan = ' colspan="' . $cell['colspan'] . '" ';
											}
										}
										if ($row['headers'] == true){
											$html .= '<th class="col_' . ($ii + 1) . '" ' . $colspan . '>' . $cell['text'] . '</th>';
										} else {
											$html .= '<td class="col_' . ($ii + 1) . '" ' . $colspan . '>' . $cell['text'] . '</td>';
										}
									}
								} else {
									$colspan = '';
									if (isset($row[$i]['colspan'])){
										if ($row[$i]['colspan'] > 1){
											$colspan = ' colspan="' . $row[$i]['colspan'] . '" ';
										}
									}
									if ($row['headers'] == true){
										$html .= '<th class="col_1" ' . $colspan . '>' . $row[$i]['text'] . '</th>';
									} else {
										$html .= '<td class="col_1" ' . $colspan . '>' . $row[$i]['text'] . '</td>';
									}
								}
								
								$html .= '</tr>';
							}
						$html .= '</table>';
						$htmlArray[$i] = $html;
					break;
				}
			}
			
			$this->listItems = $listItems;
			$this->html = $htmlArray;
		}
		
		/**
		 * @name _renderLists
		 * @desc This outputs the lists as html - they require an extra step from the other types of content as they modify their sibiling elements to check if a new ul tag has to be opened or closed
		 */
		protected function _renderLists(){
			foreach ($this->listItems as $key => $listItem){
				$html = '';
				
				if (!isset($this->listItems[$key - 1])) $html .= '<ul>';
				
				if (isset($listItem['close_ul_count'])){
					for ($i = 0; $i < $listItem['close_ul_count']; $i++){
						$html .= '</ul></li>';
				
					}
				}
				$html .= '<li>' . $listItem['text'] ;
				if (isset($listItem['open_ul_count'])) $html .= '<ul>'; else $html .= '</li>';
				
				if (isset($listItem['lastClosingTags'])){
					for ($i = 0; $i < $listItem['lastClosingTags']; $i++){
						$html .= '</ul>';
						if (($i + 1) < $listItem['lastClosingTags']){
							$html .= '</li>';
						}
					}
				}
				$this->html[$key] = $html;
			}
		}
		
		/**
		 * @name render
		 * @desc Turns the ->html array into a string of text, which is stored in ->rendered
		 */
		public function render(){
			$this->_renderLists();
			
			$html = '';
			foreach ($this->html as $i => $htmlstring){
				$html .= $htmlstring;
			}
			$this->rendered = $html;
		}
		
		/**
		 * @name __toString
		 * @return string $this->html
		 */
		public function __toString(){
			if (!isset($this->html)){
				$this->toHtml();
			}
			if (!isset($this->rendered)){
				$this->render();
			}
			
			return $this->rendered;
		}
	}