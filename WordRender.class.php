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
			$activeIndentation = 0;
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
								if (isset(self::$_styles[$node['style']])){
									$styleData = self::$_styles[$node['style']];
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
						if (isset($this->parsed[$i - 1])){
							if ($this->parsed[$i - 1]['type'] != 'list_item'){
								$activeIndentation = -1;
							}
						}
						
						if ($activeIndentation < $node['indent']){
							# Indent increasing - create new ul branch
							$indentI = 0;
							for ($loopI = $activeIndentation; $loopI < $node['indent']; $loopI++ ){
								$indentI++;
							}
							if (isset($listItems[$i - 1]))
								$listItems[$i - 1]['open_ul_count'] = $indentI;
						} else if ($activeIndentation > $node['indent']){
							# Close ul branch
							$indentI = 0;
							for ($loopI = $activeIndentation; $loopI > $node['indent']; $loopI-- ){
								$indentI++;
							}
							$node['close_ul_count'] = $indentI;
						}
						
						# If there are no more list items after this branch, we want to close the ul completly
						$calcClosingTags = false;
						
						if (isset($this->parsed[$i + 1])){
							if ($this->parsed[$i + 1]['type'] != 'list_item'){
								$calcClosingTags = true;
							}
						} else $calcClosingTags = true;
						
						if ($calcClosingTags){
							$activeIndentation = 0;
							if ($node['indent'] != 0){
								$node['lastClosingTags'] = $node['indent'] + 1;
							} else $node['lastClosingTags'] = 1;
						} else $activeIndentation = $node['indent'];
						
						$listItems[$i] = $node;						
					break;
					case 'image':
						$imageInfo = explode(".", $node['name']);
						$html .=  '<img width="' . $node['w'] . '" height="' . $node['h'] . '" title="' . $imageInfo[0] . '" src="data:image/' . $imageInfo[1] . ';base64,' . $node['data'] . '" alt="" />';
						$htmlArray[$i] = $html;
					break;
					case 'table':
						$html .= '<table>';
							foreach ($node['rows'] as $intI => $row){
								if ($row['headers'] == true)
									$html .= '<tr class="headers">';
								else 
									$html .= '<tr>';
								
								if (isset($row[$intI][0])){
									foreach ($row[$intI] as $ii => $cell){
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
									if (isset($row[$intI]['colspan'])){
										if ($row[$intI]['colspan'] > 1){
											$colspan = ' colspan="' . $row[$intI]['colspan'] . '" ';
										}
									}
									if ($row['headers'] == true){
										$html .= '<th class="col_1" ' . $colspan . '>' . $row[$intI]['text'] . '</th>';
									} else {
										$html .= '<td class="col_1" ' . $colspan . '>' . $row[$intI]['text'] . '</td>';
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
				
				# If there were no list items immedeatly before this one, we are starting a new list
				if (!isset($this->listItems[$key - 1])){
					$html .= '<ul>';
					# handle the edge case of starting with an indentation
					if ($listItem['indent'] > 0){
						for ($i = 0; $i < $listItem['indent']; $i++){
							$html .= '<li><ul>';
						}
					}
				}
				
				# If the are any additional tags set to be closed we do it here
				if (isset($listItem['close_ul_count'])){
					for ($i = 0; $i < $listItem['close_ul_count']; $i++){
						$html .= '</ul></li>';
					}
				}
								
				# Apply the li text
				$html .= '<li>' . $listItem['text'];
				
				# After the text, we either want to open a new ul or close the current li
				if (isset($listItem['open_ul_count'])) $html .= '<ul>'; else $html .= '</li>';
				
				# Apply the closing tags
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
			ksort($this->html);
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