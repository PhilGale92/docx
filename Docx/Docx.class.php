<?php 
	namespace Docx {
		class Docx {
			public $fileName = '';
			public $wordUri = '';
			public $xml = array();
			public $xPath = null;
			public $images = array();
			public static $storageLinkClass = 'storage_link';
			
			public $nodes = array();
			public $styles = array();
			
			private $_allowEmfImages = false;
			private $_htmlArr = array();
			
			protected $_passUnderStorage = array();
			protected $_currentPassUnderKey = null;
			
			public function __construct($fileUri, $fileName, $disableExternalEntities = true){
				libxml_disable_entity_loader($disableExternalEntities ) ;
				
				$this->fileName = $fileName;
				$this->wordUri = $fileUri;
				Node::$counter = -1;
			}
			
			/**
			 * @name attachStyles
			 * @desc This function takes an unlimited number of args of Docx\Style instances
			 * @return $this, modifies $this->styles
			 */
			public function attachStyles(){
				$args = func_get_args();
				foreach ($args as $arg){
					$this->styles[$arg->wordStyle] = $arg;
				}
				return $this;
			}
			
			/**
			 * @desc This method gets the required XML snippets from the file, and extracts the image assets
			 * @return $this, modifies $this->xml, $this->assets
			 */
			public function import(){
				$imageAssets = array();
				
				$zip = zip_open($this->wordUri);
				while ($zipEntry = zip_read($zip)){
					$entryName = zip_entry_name($zipEntry);
					if (zip_entry_open($zip, $zipEntry) == FALSE) continue;
					
					# /media/ contains all assets
					if (strpos($entryName, 'word/media') !== false){
						# Removes 'word/media' prefix
						$imageName = substr($entryName, 11);
						
						# Prevent EMF file extensions passing, as they are used by word rather than being manually placed
						if (!$this->_allowEmfImages){
							if (substr($imageName, -3) == 'emf') continue;
						}
						
						# Place the image assets into an array for future reference
						$imageAssets[$imageName] = array(
							'h' => 'auto',
							'w' => 'auto',
							'title' => $imageName,
							'id' => null,
							'data' => base64_encode(zip_entry_read($zipEntry, zip_entry_filesize($zipEntry))));
					}
					
					# Get the image relationship xml structure
					if ($entryName == 'word/_rels/document.xml.rels')
						$this->xml['image'] = zip_entry_read($zipEntry, zip_entry_filesize($zipEntry));
					
					# Get the document structure
					if ($entryName == 'word/document.xml')
						$this->xml['structure'] = zip_entry_read($zipEntry, zip_entry_filesize($zipEntry));
					
					zip_entry_close($zipEntry);
				}
				zip_close($zip);
				
				# Apply relId's to the image array, so they can be attached to the structure
				if (isset($this->xml['image'])){
					$dom = new \DOMDocument();
					$dom->loadXML($this->xml['image'], LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
					$dom->encoding = 'utf-8';
					$elements = $dom->getElementsByTagName('*');
					foreach ($elements as $node) {
						if ($node->nodeName == 'Relationship'){
							$relationshipAttributes = $node->attributes;
							$relationId = $relationshipAttributes->item(0);
							$relationTarget = $relationshipAttributes->item(2);
							if (is_object($relationId) && is_object($relationTarget)){
								if (strpos($relationTarget->nodeValue, 'media/') !== false){
									$imageName = substr($relationTarget->nodeValue, 6);
									$imageAssets[$imageName]['id'] = $relationId->nodeValue;
								}
							}
						}
					}
					$this->images = $imageAssets;
				}
				return $this;
			}
			
			/**
			 * @name getNodes
			 * @desc Convert the XML string into a nodeArray ($this->nodes)
			 * @return \Docx\Docx
			 */
			public function getNodes(){
				$dom = new \DOMDocument();
				$dom->loadXML($this->xml['structure'], LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
				$dom->encoding = 'utf-8';
				$elements = $dom->getElementsByTagName('*');
				
				# Set up xPath for improved dom navigating
				$xPath = new \DOMXPath($dom);
				$xPath->registerNamespace('mc', "http://schemas.openxmlformats.org/markup-compatibility/2006");
				$xPath->registerNamespace('wp', "http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing");
				$xPath->registerNamespace('w', "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
				$xPath->registerNamespace('a', "http://schemas.openxmlformats.org/drawingml/2006/main");
				$xPath->registerNamespace('pic', "http://schemas.openxmlformats.org/drawingml/2006/picture");
				$xPath->registerNamespace('v', "urn:schemas-microsoft-com:vml");
				$this->xPath = $xPath;
				
				foreach ($elements as $node) {
					# Use a switch here to decide what nodes to actually parse
					switch ($node->nodeName){
						case 'w:p':
						case 'w:drawing':
						case 'w:txbxContent':
						case 'w:tbl':
							$nodeParse = new \Docx\Node($node, $this);
						break;
					}
				}
				
				return $this;
			}
			
			/**
			 * @name parseNodes
			 * @desc Prepare the array (prepare lists, as they can affect their sibiling nodes) by modifying $node->prepend & $node->append with the ul / li tags)
			 * @return \Docx\Docx
			 */
			public function parseLists(){
				$currentListLevel = 0;
				foreach ($this->nodes as $i => $node){
					# Get the style so we can apply it to the li tag
					if ($node->listLevel > 0) $node->type = 'listitem';
					
					$liClassStr = '';
					
					if ($node->wordStyle != ''){
						$styleData = Style::getStyleObject($node->wordStyle, $this);
						if (is_object($styleData)){
							if ($styleData->htmlClass != '')
								$liClassStr = ' class="' . $styleData->htmlClass . '"';
						}
					}
					if ($currentListLevel > $node->listLevel){
						for ($loopI = $currentListLevel; $loopI > $node->listLevel; $loopI--){
							$this->nodes[$i - 1]->append .= '</li></ul>';
						}
					} else {
						if ($currentListLevel > 0 && $currentListLevel == $node->listLevel) $this->nodes[$i - 1]->append .= '</li>';
					}
					if ($currentListLevel < $node->listLevel){
						for ($loopI = $currentListLevel; $loopI < $node->listLevel; $loopI++){
							$node->prepend .= '<ul><li' . $liClassStr . '>';
						}
					} else {
						if ($currentListLevel > 0 && $currentListLevel == $node->listLevel) $node->prepend .= '<li' . $liClassStr . '>';
					}

					$currentListLevel = $node->listLevel;
				}
				return $this;
			}
			
			public static function prepStorage($node, $styleData, $str){
				if ($node->type != 'listitem'){
					$str = strip_tags($str);
					$id = Node::buildHtmlId($str, $styleData);
					$str = '<a class="' . self::$storageLinkClass . '" href="#' . $id . '">' . $str . '</a>';
					return $str;
				} else return '';
			}
			
			/**
			 * @name parseNodes
			 * @desc Converts the prepared lists of nodes into an html array
			 */
			public function parseNodes(){
				foreach ($this->nodes as $i => &$node){
					$html = '';
					switch ($node->type){
						case 'w:p':
						case 'listitem':
							if ($node->type == 'listitem') $html .= $node->prepend;
							
							$elementPrepend = '';
							$elementAppend = '';
							$idAttr = '';
							
							if ($node->type == 'w:p'){
								# List styles are delt with within the ->parseLists method, here we only want to deal with w:p
								if ($node->wordStyle == ''){
									$elementPrepend .= '<p>';
									$elementAppend .= '</p>';
								} else {
									$styleData = Style::getStyleObject($node->wordStyle, $this);
									if (is_object($styleData)){
										if ($styleData->addHtmlId){
											# Compile the text from the runarr without the prepend / appending
											$rawStr = '';
											foreach ($node->run as $runArr)
												$rawStr .= $runArr['text'];
											
											# Constuct an htmlId, then use the styleData to decide what to do with it
											$htmlId = Node::buildHtmlId($rawStr, $styleData);
											$idAttr = ' id="' . $htmlId . '"';
											
										}
																											
										$classStr = '';
										if ($styleData->htmlClass != '')
											$classStr = ' class="' . $styleData->htmlClass . '"';
								
										$elementPrepend .= '<' . $styleData->htmlTag . $classStr . $idAttr .  '>';
										$elementAppend .= '</' . $styleData->htmlTag . '>';
									}
								}
							}
							
							$html .= $elementPrepend;
							
							# Apply the w:indent
							if ($node->indent != null)
								$html .= '<span class="indent ind_' . $node->indent . '">&nbsp;</span>';
							
							
							foreach ($node->run as $ii => $runArr){
								$runPrepend = '';
								$runAppend = '';
																
								if ($runArr['bold']){ $runPrepend = '<b>' . $runPrepend; $runAppend .= '</b>';}
								if ($runArr['underline']){ $runPrepend = '<u>' . $runPrepend; $runAppend .= '</u>';}
								if ($runArr['italic']){ $runPrepend = '<i>' . $runPrepend; $runAppend .= '</i>';}
								
								if ($runArr['superscript']){ $runPrepend = '<sup>' . $runPrepend; $runAppend .= '</sup>';}
								if ($runArr['subscript']){ $runPrepend = '<sub>' . $runPrepend; $runAppend .= '</sub>';}
								
								if ($runArr['tab'] == true) $runArr['text'] = '<span class="tab"></span>' . $runArr['text'];
								
								$html .= $runPrepend . $runArr['text'] . $runAppend;
							}
							
							$html .= $elementAppend;
							if ($node->type == 'listitem') $html .= $node->append;
							
							# Now the HTML has been pasrsed run the Pass under style segment
							if (isset($styleData->passUnderNextStyle)){
								if ($styleData->passUnderNextStyle != ''){
									if (!isset($this->_passUnderStorage[$styleData->passUnderNextStyle]))
										$this->_passUnderStorage[$styleData->passUnderNextStyle] = array();
										
									if (!isset($this->_passUnderStorage[$styleData->passUnderNextStyle][$this->_currentPassUnderKey]))
										$this->_passUnderStorage[$styleData->passUnderNextStyle][$this->_currentPassUnderKey] = array();
									
									$this->_passUnderStorage[$styleData->passUnderNextStyle][$this->_currentPassUnderKey][] = static::prepStorage($node, $styleData, $html);
								}
							}

							
						break;
						case 'w:drawing':
							$imageInfo = explode(".", $node->img['name']);
							$html .=  '<img width="' . $node->img['w'] . '" height="' . $node->img['h'] . '" title="' . $imageInfo[0] . '" src="data:image/' . $imageInfo[1] . ';base64,' . $node->img['data'] . '" alt="" />';
						break;
						case 'w:tbl':
							$html .= $node->html;
						break;
						case 'w:txbxContent':
							
						break;
					}
					$node->htmlProcess = $html;
				}
				
				$this->_parseTable();
				return $this;
			}
			
			/**
			 * @name _parseTable
			 * @desc Replace tokenized cells with contents
			 */
			private function _parseTable(){
				$cellRegex = "/{CELL_PLACEHOLDER_ID_(.*?)}/";
				foreach ($this->nodes as &$node){
					if ($node->type == 'w:tbl'){
						preg_match_all($cellRegex, $node->htmlProcess, $c, PREG_PATTERN_ORDER);
						foreach ($c[1] as $i => $match){
							$cellHtml = $this->nodes[$match]->htmlProcess;
							$node->htmlProcess = str_replace($c[0][$i], $cellHtml, $node->htmlProcess);
							unset($this->nodes[$match ]);
						}
					}
				}
			}
			
			/**
			 * @name twipToPt
			 * @desc Converts the most stupid measurement into one understood by everyone
			 * @param numeric $twip
			 * @return number
			 */
			public static function twipToPt($twip){
				return round($twip / 20);
			}
			
			/**
			 * @name attachNode
			 * @param Docx\Node $nodeObj
			 * @desc Attaches an instance of Docx\Node into $this->nodes() after removing the ->docx; reference
			 */
			public function attachNode($nodeObj){
				unset($nodeObj->docx);
				$this->nodes[] = $nodeObj;
			}
			
			/**
			 * @name render
			 * @desc Turns the html array into a string ready to output
			 */
			public function render(){
				$html = '';
				foreach ($this->nodes as $node){
					$storedHtml = '';
					# Render Stored elements (if any)
					$styleData = Style::getStyleObject($node->wordStyle, $this);
					if (is_object($styleData)){
						
						if (isset($this->_passUnderStorage[$styleData->htmlClass])){
							if (isset($this->_passUnderStorage[$styleData->htmlClass][$this->_currentPassUnderKey])){
								$res = $this->_passUnderStorage[$styleData->htmlClass][$this->_currentPassUnderKey];
								foreach ($res as $subhtml){
									$storedHtml .= $subhtml;
								}
								unset($this->_passUnderStorage[$styleData->htmlClass][$this->_currentPassUnderKey]);
							}	
						}
					}
					
					
					# Case to remove empty <li>'s 
					$node->htmlProcess = str_replace(array("<li></li>", "<p></p>"), "", $node->htmlProcess);
					
					$html .= $node->htmlProcess;
					
					$html .= $storedHtml;
				}
				$this->html = $html;
			}
			
			
		}
	}