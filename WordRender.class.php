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
			$html = '';
			foreach ($this->parsed as $i => $node){
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
									# die('fatal error: ' . $node['style'] . ' style not found within WordRender::_styles');
								}
							}
						}
					break;
					case 'image':
						$imageInfo = explode(".", $node['name']);
						$html .=  '<img width="' . $node['w'] . '" height="' . $node['h'] . '" title="' . $imageInfo[0] . '" src="data:image/' . $imageInfo[1] . ';base64,' . $node['data'] . '" alt="" />';
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
					break;
				}
			}
			$this->html = $html;
		}
		
		/**
		 * @name __toString
		 * @return string $this->html
		 */
		public function __toString(){
			if (!isset($this->html)){
				$this->toHtml();
			}
			
			return $this->html;
		}
	}
