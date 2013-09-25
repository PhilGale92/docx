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
		 * @name _xPath
		 * @desc Stores the xPath object
		 * @var xPath object
		 */
		protected $_xPath = null;
		/**
		 * @name _tableOpen
		 * @desc If set to true the table parser is currently in progress
		 * @var boolean
		 */
		protected $_tableOpen = false;
		/**
		 * @name _skipCountP
		 * @desc Stores the amount of P tags to skip after a table has been rendered
		 * @var numeric
		 */
		protected $_skipCountP = 0;
		/**
		 * @name _lastPTag
		 * @desc Stores the key of the most recent P tag to have been inserted
		 * @var numeric
		 */
		protected $_lastPTag = 0;
		/**
		 * @name _images
		 * @desc Stores an array of all images found in the document file
		 * @var array
		 */
		protected $_images = array();
		
		
		protected static $_tabPlaceholder = "{[_EXTRACT_TAB_PLACEHOLDER]}";
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
		 * @name $convertInlineHtml
		 * @desc Set to TRUE to convert all {[*_PLACEHOLDER]} tokens to html strings using _parseText(), or FALSE to keep the tokenised strings
		 * @var boolean - defaults to TRUE
		 * 
		 */
		public $convertInlineHtml = true;
		
		public function __construct($fileUri){
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
			$this->_matchImages();
			$this->_parseXml();
		}
		
		
		/**
		 * @name _getXmlDump
		 * @desc Takes a file URI of a docx file, and extracts the structure by unzipping it and retriving the contents of document.xml
		 * @return boolean ($this->_rawXml)
		 */
		protected function _getXmlDump(){
			$content = '';
			$imageMatching = '';
			
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
					$imageMatching = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				}
				
				# document.xml contains all the content & structure of the file
				if ($entryName == "word/document.xml"){
					$content = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				}
				
				zip_entry_close($zip_entry);
			}
			zip_close($zip);
			$this->_images = $imageData;
			$this->_rawXml = $content;
			$this->_imageMatching = $imageMatching;
		}
		
		/**
		 * @name _matchImages
		 * @desc This method uses $this->imageMatching to pull the correct image files into the document structure
		 */
		protected function _matchImages(){
			if ($this->_imageMatching != ''){
				$dom = new \DOMDocument();
				$dom->loadXML($this->_imageMatching, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
				$dom->encoding = $this->encoding;
				$elements = $dom->getElementsByTagName('*');
				foreach ($elements as $node) {
					if ($node->nodeName == 'Relationship'){
						$nodeArr = self::_getArray($node);
						if (strpos($nodeArr['Target'], 'media/') !== false){
							if (isset($nodeArr['Target']) && isset($nodeArr['Id'])){
								$imageName = substr($nodeArr['Target'], 6);
								$this->_images[$imageName]['id'] = $nodeArr['Id'];
							}
						}
					}
					
				}
				
			}
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
				$styleArray = self::_getArray($styleResult);
				if (isset($styleArray['w:val'])){
					$style = $styleArray['w:val'];
					break;
				}
			}
			return $style;
		}
		
		/**
		 * @name _parseXml
		 * @desc Converts the raw XML string into an array of nodes of either text (with styles attached), tables or images
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
			
			# This stage gets the appropriate node[s] from the document, and passes it into the _parseNode() method
			foreach ($elements as $node) {
				switch ($node->nodeName){
					
					# Paragraph parsing
					case 'w:p':
						if (!$this->_tableOpen){
							$this->_curStyle = $this->_parseNodeStyle($node);
							$parsedArr = $this->_parseNode($node, 'p');
							if ($parsedArr != null){
								$this->parsed[] = $parsedArr;
								$this->_lastPTag = count($this->parsed) - 1;
							}
						}
					break;
					
					# Image parsing
					case 'w:drawing':
						# Get the blipFill for the imageRefId
						$mcAltContentXPath = $xPath->query("*/a:graphic/a:graphicData/pic:pic/pic:blipFill", $node);
						$imageData = array();
						
						foreach ($mcAltContentXPath as $blipFill){
							# The blip however is always required to get the imageRefId
							if ($blipFill->nodeName == null) continue;
							$imageData['blip'] = $blipFill;
						}
						
						# Get the prev. element to load the alterateContent block
						$prevElement = $node->parentNode->previousSibling;
						if (!isset($prevElement->nodeName)) continue;
						
						# Load the alt Content for the dimensions
						$mcDimensionXPath = $xPath->query("mc:AlternateContent/mc:Fallback/w:pict/v:rect", $prevElement);
						foreach ($mcDimensionXPath as $dimensionWrapper){
							# If 'rect' is not found, we just use image width/height = auto so it is not required
							if ($dimensionWrapper->nodeName != null)
								$imageData['rect'] = $dimensionWrapper;
						}
						
						$parsedArr = $this->_parseNode($imageData, 'image');
						if ($parsedArr != null){
							$this->parsed[] = $parsedArr;
						}
					break;
					
					# Table parsing
					case 'w:tbl':
						$parsedArr = $this->_parseNode($node, 'table');
						if ($parsedArr != null)
							$this->parsed[] = $parsedArr;
					break;
				}
			}
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
			$row = self::_getArray($wrObject);
			$text = '';
			# Bold
			if (isset($row['w:rPr'][0]['w:b'])){
				$textPrepend .= self::$_boldPlaceholder[0];
				$textAppend = self::$_boldPlaceholder[1] . $textAppend;
			}
				
			# Italics
			if (isset($row['w:rPr'][0]['w:i'])){
				$textPrepend .= self::$_italicsPlaceholder[0];
				$textAppend = self::$_italicsPlaceholder[1] . $textAppend;
			}
				
			# Underlines
			if (isset($row['w:rPr'][0]['w:u'])){
				$textPrepend .= self::$_underlinePlaceholder[0];
				$textAppend = self::$_underlinePlaceholder[1] . $textAppend;
			}
				
			# Tabs
			if (isset($row['w:tab'])){
				$text .= self::$_tabPlaceholder;
			}
				
			if (isset($row['w:t'][0]['#text'])){
				$text .= $textPrepend . $row['w:t'][0]['#text'] . $textAppend;
			} else {
				if (isset($row['w:t'])){
					if (!is_array($row['w:t'])){
						$text .= $textPrepend . $row['w:t'] . $textAppend;
					}
				}
			}
			return $text;
		}
		
		/**
		 * @name _parseNode
		 * @desc Retrives the data from a given '*' node from the xml
		 * @param domobject $node
		 * @param string $type
		 * @return NULL|multitype:string
		 */
		protected function _parseNode($node, $type){
			$inlineBoldFlag = false;
			$inlineUnderlineFlag = false;
			$inlineItalicsFlag = false;
			$wordStyleOverride = null;
			
			if ($type == 'p'){
				$text = '';
				$nodeHasRow = false;
				
				foreach ($node->childNodes as $child){
					if ($child->nodeName == 'w:r' || $child->nodeName == 'w:hyperlink'){
						if ($nodeHasRow == false){
							if ($this->_skipCountP > 0){
								$this->_skipCountP--;
								return null;
							}
							$nodeHasRow = true;
						}
					}
					
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
					$listArray = self::_getArray($listResult);
				}
				if (!empty($listArray)){
					$indent = 0;
					if (isset($listArray['w:ilvl'][0]['w:val'])){
						$indent = $listArray['w:ilvl'][0]['w:val'];
					}
					$parsedNode = array(
						'type' => 'list_item',
						'style' => $this->_curStyle,
						'text' => $text,
						'indent' => $indent,
					);
					return $parsedNode;
				}
				
				$style = $this->_curStyle;
				
				if ($text == '') return null;
				
				$parsedNode = array(
					'text' => $this->_parseInlineLists($text),
					'type' => 'p',
					'style' => $style
				);
			
			}
			
			if ($type == 'table'){
				$nodeArray = self::_getArray($node);
				$this->_tableOpen = true;
				
				$columnCount = count($nodeArray['w:tblGrid'][0]['w:gridCol']);
				$rowCount = count($nodeArray['w:tr']);
				$parsedNode = array(
					'rows' => array(),
					'type' => 'table',
					'columnCount' => $columnCount,
					'rowCount' => $rowCount,
				);
				$counter = -1;				
				foreach ($nodeArray['w:tr'] as $i => $tableRow){
					$counter++;
					
					$row = array(
						$counter => array(),
					);
					if ($i == 0)
						$row['headers'] = true;
					else 
						$row['headers'] = false;
					

					# Row has multiple columns
					if (is_array($tableRow['w:tc'])){
						foreach ($tableRow['w:tc'] as $ii => $tableCell){
							
							$cellText = '';
							if (isset($tableCell['w:p'][0]['w:r'])){
								foreach ($tableCell['w:p'] as $tableRow){
									if (isset($tableRow['w:r'])){
										$this->_skipCountP++;
										foreach ($tableRow['w:r'] as $iii => $tableCellRow){
											if (isset($tableCellRow['w:t'][0]['#text'])){
												$cellText .= $tableCellRow['w:t'][0]['#text'] . ' ';
											}
										}
									}
								}
							}
							
							$cellText = $this->_parseText(trim($cellText));
							
							$row[$counter][] = array(
								'text' => $this->_parseInlineLists($cellText),
								'colspan' => 1,
							);
						}
					} else {
						$this->_skipCountP += $rowCount;
						
						# Row has single col
						$row[$counter]['colspan'] = $columnCount;
						$row[$counter]['text'] = $this->_parseInlineLists($this->_parseText($tableRow['w:tc']));
					}
					$parsedNode['rows'][] = $row;
				}
				$this->_tableOpen = false;
			}
			
			if ($type == 'image'){
				# Embed an image - images are passed as an array of 'blip' => blipNode, 'rect' => rectNode
				if (isset($node['blip'])){
					$blipArr = self::_getArray($node['blip']);
					$imageToUseId = $blipArr['a:blip'][0]['r:embed'];
					$imageData = self::_array_complex_search($this->_images, 'id', $imageToUseId);
					
					if (!is_array($imageData)) return null;
					
					# Defaults are initally set as 'auto'
					if (!isset($imageData[0]['w'])) return null;
					
					$w = $imageData[0]['w'];
					$h = $imageData[0]['h'];
					
					# Load the rect if available to load the image dimensions
					if (isset($node['rect'])){
						$rectData = self::_getArray($node['rect']);
						if (isset($rectData['style'])){
							$imageStyles = $rectData['style'];
							$imageStyleArray = explode(";", $imageStyles);
							foreach ($imageStyleArray as $imageStyle){
								$styleInfo = explode(":", $imageStyle);
								if (strtolower($styleInfo[0]) == 'width')
									$w = $styleInfo[1];
								
								if (strtolower($styleInfo[0]) == 'height')
									$h = $styleInfo[1];
							}
						}
					}
					$parsedNode = array(
							'type' => 'image',
							'name' => $imageData[0]['title'],
							'h' => $h,
							'w' => $w,
							'data' => $imageData[0]['data']
					);
					
				} else $parsedNode = null;
			}
			
			return $parsedNode;
		}
		
		/**
		 * @name _parseText
		 * @desc Parses out any html-invalid charecters from the string into html entities, before the processing gets too far along
		 * @param string $text
		 * @return string $processedText
		 */
		protected function _parseText($text){
			$text = htmlentities($text, ENT_QUOTES, $this->encoding);
			if ($this->convertInlineHtml){
				$text = str_replace(self::$_tabPlaceholder, '<span class="tab_placeholder"></span>', $text);
				$text = str_replace(self::$_italicsPlaceholder, array('<i>', '</i>'), $text);
				$text = str_replace(self::$_boldPlaceholder, array('<b>', '</b>'), $text);
				$text = str_replace(self::$_underlinePlaceholder, array('<span class="underline">', '</span>') , $text);
				
				$text = str_replace(self::$_hrefPlaceholder, array('<a href="', '">', '</a>'), $text);
			}
			
			$processedText = nl2br($text);
			
			return $processedText;
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
		 * @name _getArray
		 * @desc Converts a dom object into a PHP array - utility method
		 * @param domobject $node
		 * @return array
		 */
		protected static function _getArray($node){
			$array = false;
			if ($node->hasAttributes()){
				foreach ($node->attributes as $attr){
					$array[$attr->nodeName] = $attr->nodeValue;
				}
			}
			
			if ($node->hasChildNodes()){
				if ($node->childNodes->length == 1)	{
					$array[$node->firstChild->nodeName] = $node->firstChild->nodeValue;
				} else {
					foreach ($node->childNodes as $childNode){
						if ($childNode->nodeType != XML_TEXT_NODE)	{
							$array[$childNode->nodeName][] = self::_getArray($childNode);
						}
					}
				}
			}
		
			return $array;
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