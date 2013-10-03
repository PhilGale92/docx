<?php
	/**
	 * @author Phil Gale - philgale.co.uk
	 * @desc Just create an instance of this object while passing an absolute uri to the .docx file, and watch the magic happen
	 * @name WordExtractor
	 */
	class WordExtractor {
		/**
		 * @name _rawXml
		 * @desc Stores the xml of the document structure
		 * @var string
		 */
		private $_rawXml = '';
		/**
		 * @name _imageReference
		 * @desc Stores the xml of the image relationships
		 * @var string
		 */
		private $_imageReference = '';
		/**
		 * @name _curStyle
		 * @desc Stores id of the current row style
		 * @var string
		 */
		protected $_curStyle = '';
		/**
		 * @name _curIndent
		 * @desc Stores value of the current line indentation (null means no indent)
		 * @var string
		 */
		protected $_curIndent = null;
		/**
		 * @name _xPath
		 * @desc Stores the xPath object
		 * @var xPath object
		 */
		protected $_xPath = null;
		/**
		 * @name _lastPTag
		 * @desc Stores the key of the most recent P tag to have been inserted
		 * @var numeric
		 */
		protected $_lastPTag = 0;
		/**
		 * @name _currentRowCounter
		 * @desc Stores the current count of the row within w:p nodes
		 * @var numeric
		 */
		protected $_currentRowCounter = 0;
		/**
		 * @name _images
		 * @desc Stores an array of all images found in the document file
		 * @var array
		 */
		protected $_images = array();
				
		protected static $_tabPlaceholder = "{[_EXTRACT_TAB_PLACEHOLDER]}";
		protected static $_indentPlaceholder = array("{[_EXTRACT_INDENT_PLACEHOLDER]}", "{[_EXTRACT_ENDSTYLE_INDENT_PLACEHOLDER]}");
		protected static $_boldPlaceholder = array('{[_BOLD_OPEN_PLACEHOLDER]}', '{[_BOLD_CLOSE_PLACEHOLDER]}');
		protected static $_italicsPlaceholder = array('{[_EMPHASIZE_OPEN_PLACEHOLDER]}', '{[_EMPHASIZE_CLOSE_PLACEHOLDER]}');
		protected static $_underlinePlaceholder = array('{[_UNDERLINE_OPEN_PLACEHOLDER]}', '{[_UNDERLINE_CLOSE_PLACEHOLDER]}');
		protected static $_hrefPlaceholder = array('{[_HREF_OPEN_PLACEHOLDER]}', '{[_HREF_MID_PLACEHOLDER]}' , '{[_HREF_CLOSE_PLACEHOLDER]}');
		
		/**
		 * @name encoding
		 * @var string $encoding
		 * @desc Used to set the encoding on domdocument objects
		 */
		public $encoding = 'utf-8';
		
		/**
		 * @name fileName
		 * @var string $fileName
		 * @desc Stores the file name of the extracted docx file
		 */
		public $fileName = '';
				
		/**
		 * @name $convertInlineHtml
		 * @desc Set to TRUE to convert all {[*_PLACEHOLDER]} tokens to html strings using _parseText(), or FALSE to keep the tokenised strings
		 * @var boolean - defaults to TRUE
		 * 
		 */
		public $convertInlineHtml = true;
		
		public function __construct($fileUri){
			$this->fileName = basename($fileUri);			
			$this->wordUri = $fileUri;
			$this->encoding = 'utf-8';
			$this->convertInlineHtml = true;
			return $this;
		}
		
		public function config($args){
			foreach ($args as $k => $v){
				$this->$k = $v;
			}
			return $this;
		}
		
		public function extract(){
			$this->_getXmlDump();
			$this->_parseXml();
		}
		
		
		/**
		 * @name _getXmlDump
		 * @desc Takes a file URI of a docx file, and extracts the structure by unzipping it and retriving the contents of document.xml
		 * @return string $this->_rawXml, string $this->_imageMatching, array $this->_images
		 */
		protected function _getXmlDump(){
			$xmlStructure = '';
			$imageRelationships = '';
			
			$zip = zip_open($this->wordUri);
			if (!$zip || is_numeric($zip)) return false;
			
			$imageData = array();
			while ($zip_entry = zip_read($zip)) {
				$entryName = zip_entry_name($zip_entry);
				
				if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
				
				# /media/ contains all included images
				if (strpos($entryName, 'word/media') !== false){
					$imageName = substr($entryName, 11);
					
					# Check to prevent 'emf' file extensions passing. emf files are used by word rather than being added into the document by hand 
					# ~ and can corrupt renderers that do not expect these hidden images.
					if (substr($imageName, -3) == 'emf') continue;
					$imageData[$imageName] = array('h' => 'auto', 'w' => 'auto', 'title' => $imageName, 'id' => null, 'data' => base64_encode(zip_entry_read($zip_entry, zip_entry_filesize($zip_entry))));
				}
				
				# document.xml.refs supplies relationships of Id's to image url's
				if ($entryName == 'word/_rels/document.xml.rels'){
					$imageRelationships = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				}
				
				# document.xml contains all the content & structure of the file
				if ($entryName == "word/document.xml"){
					$xmlStructure = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				}
				
				zip_entry_close($zip_entry);
			}
			zip_close($zip);
			
			$this->_rawXml = $xmlStructure;
			$this->_images = $imageData;
			
			# This segment uses $imageRelationships to pull the correct image files into the document structure and modify $_images[$key]['id'] = CorrectValue
			if ($imageRelationships != ''){
				$dom = new \DOMDocument();
				$dom->loadXML($imageRelationships, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
				$dom->encoding = $this->encoding;
				$elements = $dom->getElementsByTagName('*');
				foreach ($elements as $node) {
					if ($node->nodeName == 'Relationship'){
						$relationshipAttributes = $node->attributes;
						$relationId = $relationshipAttributes->item(0);
						$relationTarget = $relationshipAttributes->item(2);
						if (is_object($relationId) && is_object($relationTarget)){
							if (strpos($relationTarget->nodeValue, 'media/') !== false){
								$imageName = substr($relationTarget->nodeValue, 6);
								$this->_images[$imageName]['id'] = $relationId->nodeValue;
							}
						}
					}
				}
			}
		}
				
		/**
		 * @name _parseXml
		 * @desc Converts the raw XML data into a PHP array
		 */
		protected function _parseXml(){
			$dom = new \DOMDocument();
			$dom->loadXML($this->_rawXml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
			$dom->encoding = $this->encoding;
			$elements = $dom->getElementsByTagName('*');
				
			# Set up xPath for image parsing
			$xPath = new DOMXPath($dom);
			$xPath->registerNamespace('mc', "http://schemas.openxmlformats.org/markup-compatibility/2006");
			$xPath->registerNamespace('wp', "http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing");
			$xPath->registerNamespace('w', "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
			$xPath->registerNamespace('a', "http://schemas.openxmlformats.org/drawingml/2006/main");
			$xPath->registerNamespace('pic', "http://schemas.openxmlformats.org/drawingml/2006/picture");
			$xPath->registerNamespace('v', "urn:schemas-microsoft-com:vml");
			$this->_xPath = $xPath;
		
			# This stage gets the appropriate domelements from the document, and passes them into the _parse() / _parseContainer methods
			foreach ($elements as $node) {
				switch ($node->nodeName){
					case 'w:p':
						if ($node->parentNode->nodeName == 'w:txbxContent') continue;
						if ($node->parentNode->nodeName == 'w:tc') continue;
						if ($node->parentNode->parentNode->nodeName == 'w:tc') continue;
						$parsedArr = $this->_parse($node, 'w:p');
						if ($parsedArr != null){
							$this->parsed[] = $parsedArr;
							$this->_lastPTag = count($this->parsed) - 1;
						}
					break;
					case 'w:drawing':
						if ($node->parentNode->nodeName == 'w:txbxContent') continue;
						if ($node->parentNode->nodeName == 'w:tc') continue;
						if ($node->parentNode->parentNode->parentNode->nodeName == 'w:tc') continue;
						$parsedArr = $this->_parse($node, 'w:drawing');
						if ($parsedArr != null){
							$this->parsed[] = $parsedArr;
						}
					break;
					case 'w:txbxContent':
						$parsedArr = $this->_parseContainer($node, 'w:txbxContent');
						if ($parsedArr != null)
							$this->parsed[] = $parsedArr;
					break;
					case 'w:tbl':
						$parsedArr = $this->_parseContainer($node, 'w:tbl');
						if ($parsedArr != null)
							$this->parsed[] = $parsedArr;
					break;
				}
			}
		}
		
		/**
		 * @name _parse
		 * @param $node domElement
		 * @param $nodeType string
		 * @desc Parses a domElement depending on its type
		 * @return $lastParsedNode array
		 */
		protected function _parse($node, $nodeType){
			$matchedNode = false;
			$lastParsedNode = null;
			
			switch ($nodeType){
				case 'w:p':
					$this->_currentRowCounter = 0;
					
					$this->_curStyle = $this->_parseNodeStyle($node);
					$this->_curIndent = $this->_parseNodeIndent($node);
					
					$text = '';
					foreach ($node->childNodes as $child){
						
						# standard text handler
						if ($child->nodeName == 'w:r'){
							$text .= $this->_parseWR($child);
						}
						
						# link handler
						if ($child->nodeName == 'w:hyperlink'){
							$hyperlinkQuery = $this->_xPath->query("w:r/w:t", $child);
							$hyperlink = '';
							foreach ($hyperlinkQuery as $hyperlinkRes){
								$hyperlink = $hyperlinkRes->nodeValue;
								$rowObj = $hyperlinkRes->parentNode;
							}
							if ($hyperlink != ''){
								if (substr($hyperlink, 0, 4) != 'http') $hyperlink = 'http://' . $hyperlink;
								$text .= $this->_parseWR($rowObj, self::$_hrefPlaceholder[0] . $hyperlink . self::$_hrefPlaceholder[1], self::$_hrefPlaceholder[2]);
							}
						}
					}
					
					$text = $this->_parseText($text);
					
					# List processing
					$listResQuery = $this->_xPath->query("w:pPr/w:numPr", $node);
					$listArray = array();
					foreach ($listResQuery as $listResult){
						$indent = 0;
						$indentNode = $listResult->childNodes->item(0);
						if ($indentNode->nodeName == 'w:ilvl'){
							$indent = $indentNode->attributes->item(0)->nodeValue;
						}
							
						$lastParsedNode = array(
							'type' => 'list_item',
							'style' => $this->_curStyle,
							'text' => $text,
							'indent' => $indent,
						);
						$matchedNode = true;
					}
					if (!$matchedNode){
						$style = $this->_curStyle;
						if ($text != ''){
							$inlineText = $this->_parseInlineLists($text);
							$lastParsedNode = array(
								'text' => $inlineText,
								'type' => 'p',
								'style' => $style
							);
							$matchedNode = true;
						}
					}
				break;
				case 'w:drawing':
					# Get the blipFill for the imageRefId
					$mcAltContentXPath = $this->_xPath->query("*/a:graphic/a:graphicData/pic:pic/pic:blipFill", $node);
					$blipNode = $rectNode = null;
					
					foreach ($mcAltContentXPath as $blipFill){
						# The blip however is always required to get the imageRefId
						if ($blipFill->nodeName == null) continue;
						$blipNode = $blipFill;
					}
					
					# Get the prev. element to load the alterateContent block
					$prevElement = $node->parentNode->previousSibling;
					if (!isset($prevElement->nodeName)) continue;
					
					# Load the alt Content for the dimensions
					$mcDimensionXPath = $this->_xPath->query("mc:AlternateContent/mc:Fallback/w:pict/v:rect", $prevElement);
						foreach ($mcDimensionXPath as $dimensionWrapper){
							# If 'rect' is not found, we just use image width/height = auto so it is not required
							if ($dimensionWrapper->nodeName != null)
								$rectNode = $dimensionWrapper;
					}
					
					# Get the imageToUseId by searching the blip node for an id
					if ($blipNode != null){
						$blipQuery = $this->_xPath->query("a:blip", $blipNode);
						foreach ($blipQuery as $blipRes){
							foreach ($blipRes->attributes as $blipEmbedNode){
								if ($blipEmbedNode->nodeName == 'r:embed'){
									$imageToUseId = $blipEmbedNode->nodeValue;
									break 2;
								}
							}
						}

						# Use the id as a key within the _images array
						$imageData = self::_array_complex_search($this->_images, 'id', $imageToUseId);
							
						if (!is_array($imageData)) return null;
							
						# If the image doesnt have a width defined, then the image parser skipped on this specific image, so skip it
						if (!isset($imageData[0]['w'])) return null;
						
						# Defaults are initally set as 'auto'
						$w = $imageData[0]['w'];
						$h = $imageData[0]['h'];
							
						# Load the rect node if available to load the image dimensions
						if ($rectNode != null){
							$rectStyles = $rectNode->attributes;
							foreach ($rectStyles as $rectStyleNode){
								if ($rectStyleNode->nodeName == 'style'){
									$imageStyleArray = explode(";", $rectStyleNode->nodeValue);
									foreach ($imageStyleArray as $imageStyle){
										$styleInfo = explode(":", $imageStyle);
										if (strtolower($styleInfo[0]) == 'width')
											$w = $styleInfo[1];
								
										if (strtolower($styleInfo[0]) == 'height')
										$h = $styleInfo[1];
									}
									break;
								}
							}
						}
						$matchedNode = true;
						
						# Collate the image into the parsed array
						$lastParsedNode = array(
							'type' => 'image',
							'name' => $imageData[0]['title'],
							'h' => $h,
							'w' => $w,
							'data' => $imageData[0]['data']
						);		
					}
				break;
			}
			
		
			if (!$matchedNode) $lastParsedNode = null;
			return $lastParsedNode;
		}
		
		/**
		 * @name _parseContainer
		 * @desc Handles domElements that contain sub-elements (eg. tables containing images / text)
		 * @param domobject $node
		 * @param string $type
		 * @return NULL|multitype:string
		 */
		protected function _parseContainer($node, $type){
			if ($type == 'w:txbxContent'){
				$textboxContentItems = array();
				foreach ($node->childNodes as $textboxChild){
					if ($textboxChild->nodeName == 'w:p')
						$textboxContentItems[] = $this->_parse($textboxChild, 'w:p');
				}
				$parsedNode = array(
					'content' => $textboxContentItems,
					'type' => 'textbox',
				);
			}
			if ($type == 'w:tbl'){
				# Retrieve the table grid info (we need column count details & row count)
				$colCountQuery = $this->_xPath->query("w:tblGrid/w:gridCol", $node);
				$columnCount = 0;
				foreach ($colCountQuery as $gridColNode){
					if ($gridColNode->nodeName == 'w:gridCol')
						$columnCount++;
				}
				
				$rowCountQuery = $this->_xPath->query("w:tr", $node);
				$rowCount = 0;
				foreach ($rowCountQuery as $rowNode){
					if ($rowNode->nodeName == 'w:tr')
						$rowCount++;
				}
								
				$parsedNode = array(
					'rows' => array(),
					'type' => 'table',
					'columnCount' => $columnCount,
					'rowCount' => $rowCount,
				);
				$rowQuery = $this->_xPath->query("w:tr", $node);
				$rowCounter = -1;
				foreach ($rowQuery as $rowNode){
					$rowCounter++;
					
					$cellQuery = $this->_xPath->query("w:tc", $rowNode);
					$cellCounter = -1;
					foreach ($cellQuery as $cellNode){
						$cellCounter++;	
						$paragraphRes = array();
						foreach ($cellNode->childNodes as $cellChildNode){
							
							# If the cell directly contains an image:
							if ($cellChildNode->nodeName == 'w:drawing'){
								$imageRes = $this->_parse($cellChildNode, 'w:drawing');
								if ($imageRes != null){
									$paragraphRes[] = $imageRes;
								}
							}
							
							# If the cell contains a paragraph
							if ($cellChildNode->nodeName == 'w:p'){
								# Iterate through each text run, so if there is an image we can spit it into the table inlined
								$singleParaRes =  $this->_parse($cellChildNode, 'w:p');
								if ($singleParaRes != null){
									$paragraphRes[] = $singleParaRes;
								}
								
								$paragraphRunRes = $this->_xPath->query("w:r", $cellChildNode);
								foreach ($paragraphRunRes as $paragraphNode){
									foreach ($paragraphNode->childNodes as $paragraphRunChild){
										if ($paragraphRunChild->nodeName == 'w:drawing'){
											$imageRes = $this->_parse($paragraphRunChild, 'w:drawing');
											if ($imageRes != null)
												$paragraphRes[] = $imageRes;
										}
									}
								}
							}
							
						}
						$parsedNode['rows'][$rowCounter]['cells'][$cellCounter] = $paragraphRes;
					}
					if ($rowCounter == 0)
						$parsedNode['rows'][$rowCounter]['headers'] = true;
					else
						$parsedNode['rows'][$rowCounter]['headers'] = false;
				}
			}

			if (!isset($parsedNode)) return null;
			return $parsedNode;
		}
		
		/**
		 * @name _parseNodeIndent
		 * @desc Takes a node and returns the nodes set indentation
		 * @param domObject $node
		 * @return numeric $indent (or NULL if there is no indent)
		 */
		protected function _parseNodeIndent($node){
			$indent = null;
			$indentQuery = $this->_xPath->query("w:pPr/w:ind", $node);
			foreach ($indentQuery as $indentRes){
				if ($indentRes->nodeName == 'w:ind'){
					foreach ($indentRes->attributes as $indentResAttr){
						if ($indentResAttr->nodeName == 'w:firstLine'){
							$indent = $indentResAttr->nodeValue;
							break 2;
						}
					}
				}
			}
			if ($indent != null){
				# Docx stores indentation as 'twips' - twentieths of a pt
				$indent /= 20;
			}
			return $indent;
		}
		
		
		/**
		 * @name _parseNodeStyle
		 * @desc Takes a node and returns the nodes set style
		 * @param domObject $node
		 * @return string $style - returns '' if no style is found
		 */
		protected function _parseNodeStyle($node){
			$styleQuery = $this->_xPath->query("w:pPr/w:pStyle", $node);
			$style = '';
			foreach ($styleQuery as $styleResult){
				foreach ($styleResult->attributes as $styleNode){
					$style = $styleNode->nodeValue;
					break 2;
				}
			}
			return $style;
		}
		
		/**
		 * @name _parseInlineLists
		 * @desc Parses plaintext for any inline lists (matches against &bull;)
		 * @param string $text
		 * @return string $text 
		 */
		protected function _parseInlineLists($text){
			static $ulOpen = false;
			$processedText = '';
			
			$textChunks = explode("&bull;", $text);
			$count = count($textChunks);
			if ($count == 1 && strpos($text, "&bull;") !== false){
				if (!$ulOpen){
					$ulOpen = true;
					$processedText = '<ul class="inline_list">';
				}
				$processedText .= '<li>' . substr($text, 6) . '</li>';
			} elseif ($count > 1) {
				$processedText = '<ul class="inline_list">';
				foreach ($textChunks as $i => $listItem){
					if ($this->convertInlineHtml){
						if ($listItem == '<b>') continue;
						if (substr($listItem, 0, 4) == '</b>') $listItem = substr($listItem, 4);
					} else {
						if ($listItem == self::$_boldPlaceholder[0]) continue;
						$placeholderLength = strlen(self::$_boldPlaceholder[1]);
						if (substr($listItem, 0, $placeholderLength) == self::$_boldPlaceholder[1]) $listItem = substr($listItem, $placeholderLength);
					}
					if (strlen(trim($listItem)) == 0) continue;
					$processedText .= '<li>' . $listItem . '</li>';
				}
				$processedText .= '</ul>';
			} else {
				if ($ulOpen){
					$ulOpen = false;
					$this->parsed[$this->_lastPTag]['text'] .= '</ul>';
				}
				$processedText = $text;
			}
			
			return $processedText;
		}
		
		/**
		 * @name _parseWR
		 * @desc Converts a WR object into text
		 * @param domobject $wrObject
		 * @param string $textPrepend
		 * @param string $textAppend
		 * @return string $text
		 */
		protected function _parseWR($wrObject, $textPrepend = '', $textAppend = ''){
			$text = '';
			
			# Bold
			$boldQuery = $this->_xPath->query("w:rPr/w:b", $wrObject);
			foreach ($boldQuery as $boldRes){
				$textPrepend .= self::$_boldPlaceholder[0];
				$textAppend = self::$_boldPlaceholder[1] . $textAppend;
			}
				
			# Italics
			$italicsQuery = $this->_xPath->query("w:rPr/w:i", $wrObject);
			foreach ($italicsQuery as $italicRes){
				$textPrepend .= self::$_italicsPlaceholder[0];
				$textAppend = self::$_italicsPlaceholder[1] . $textAppend;
			}
						
			# Underlines
			$underlineQuery = $this->_xPath->query("w:rPr/w:u", $wrObject);
			foreach ($underlineQuery as $underlineRes){
				$textPrepend .= self::$_underlinePlaceholder[0];
				$textAppend = self::$_underlinePlaceholder[1] . $textAppend;
			}
			
			# Tabs
			$tabQuery = $this->_xPath->query("w:tab", $wrObject);
			foreach ($tabQuery as $tabRes){
				$text .= self::$_tabPlaceholder;
			}
			
			# Indent - only want to apply this on the first row in the paragraph
			if ($this->_currentRowCounter == 0){
				if ($this->_curIndent != null){
					$text .= self::$_indentPlaceholder[0] . $this->_curIndent . self::$_indentPlaceholder[1];
				}
			}
			
			# Text
			$textQuery = $this->_xPath->query("w:t", $wrObject);
			foreach ($textQuery as $textRes){
				$text .= $textPrepend . $textRes->nodeValue . $textAppend;
			}

			$this->_currentRowCounter++;
			
			return $text;
		}
		
		/**
		 * @name _parseText
		 * @desc Escapes any entities from the string into html entities, and replaces the placeholders with valid html
		 * @param string $text
		 * @return string $processedText
		 */
		protected function _parseText($text){
			$text = htmlentities($text, ENT_QUOTES, $this->encoding);
			if ($this->convertInlineHtml){
				$text = str_replace(self::$_tabPlaceholder, '<span class="tab_placeholder"></span>', $text);
				$text = str_replace(self::$_indentPlaceholder, array('<span class="indent_placeholder" style="padding-left:', 'px;"></span>'), $text);
				$text = str_replace(self::$_italicsPlaceholder, array('<i>', '</i>'), $text);
				$text = str_replace(self::$_boldPlaceholder, array('<b>', '</b>'), $text);
				$text = str_replace(self::$_underlinePlaceholder, array('<span class="underline">', '</span>') , $text);
				$text = str_replace(self::$_hrefPlaceholder, array('<a href="', '">', '</a>'), $text);
			}
				
			$processedText = nl2br($text);
				
			return $processedText;
		}
				
		/**
		 * @name _array_complex_search
		 * @desc Finds a key within an associative array using multiple values - utility method
		 * @param array $array
		 * @param string $key
		 * @param string $value
		 * @return Ambigous <multitype:unknown , multitype:>
		 */
		protected static function _array_complex_search($array, $key, $value){
			$results = array();
		
			if (is_array($array)){
				if (isset($array[$key]) && $array[$key] == $value)
					$results[] = $array;
		
				foreach ($array as $subarray)
					$results = array_merge($results, self::_array_complex_search($subarray, $key, $value));
			}
			return $results;
		}
		
	}