<?php
	/**
	 * @author Phil Gale - philgale.co.uk
	 * @desc Just create an instance of this object while passing an absolute uri to the .docx file, and watch the magic happen
	 * @name WordExtractor
	 */
	class WordExtractor {
		protected $_tableOpen = false;
		protected $_skipCountP = 0;
		protected $_rawXml = '';
		protected $_imageMatching = '';
		protected $_lastPTag = 0;
		protected $_images = array();
		protected static $_tabPlaceholder = "{[_EXTRACT_TAB_PLACEHOLDER]}";
		protected static $_boldPlaceholder = array('{[_BOLD_OPEN_PLACEHOLDER]}', '{[_BOLD_CLOSE_PLACEHOLDER]}');
		protected static $_emphasizePlaceholder = array('{[_EMPHASIZE_OPEN_PLACEHOLDER]}', '{[_EMPHASIZE_CLOSE_PLACEHOLDER]}');
		
		/**
		 * @name encoding
		 * @var string $encoding
		 * @desc Used to set the encoding on domdocument objects
		 */
		public $encoding = 'utf-8';
		
		/**
		 * @name $encodingCaps
		 * @var string $encodingCaps
		 * @desc Used to set the encoding on htmlentities() functions, by default to set uppercase version of $encoding
		 */
		public $encodingCaps = '';
		
		/**
		 * @name __construct()
		 * @desc Pass the absolute path to the file, and optionally the files encoding in lowercase form & uppercase (for the differences between DOMDocument & htmlentities(); - eg. 'utf-8', 'UTF-8' - the default)
		*/
		public function __construct($fileUri, $encoding = null, $encodingCaps = null){
			if ($encoding != null){
				$this->encoding = $encoding;
			}
			if ($encodingCaps != null){
				$this->encodingCaps = $encodingCaps;
			} else {
				$this->encodingCaps = strtoupper($this->encoding);
			}
						
			$this->wordUri = $fileUri;
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
				
				if (strpos($entryName, 'word/media') !== false){
					$imageName = substr($entryName, 11);
					
					# Check to prevent 'emf' file extensions passing. emf files are used by word rather than being added into the document by hand 
					# ~ and can corrupt renderers that do not expect these hidden images.
					if (substr($imageName, -3) == 'emf') continue;
					$imageData[$imageName] = array('h' => 'auto', 'w' => 'auto', 'title' => $imageName, 'id' => null, 'data' => base64_encode(zip_entry_read($zip_entry, zip_entry_filesize($zip_entry))));
				}
				
				if ($entryName == 'word/_rels/document.xml.rels'){
					$imageMatching = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				}
				
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
			
			# This stage gets the appropriate node[s] from the document, and passes it into the _parseNode() method
			foreach ($elements as $node) {
				switch ($node->nodeName){
					# Paragraph parsing
					case 'w:p':
						if (!$this->_tableOpen){
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
		 * @name _parseNode
		 * @desc Retrives the data from a given '*' node from the xml
		 * @param domobject $node
		 * @param string $type
		 * @return NULL|multitype:string
		 */
		protected function _parseNode($node, $type){
			if ($type == 'p'){
				$nodeArray = self::_getArray($node);
				$text = '';
				if (isset($nodeArray['w:r'])){
					if ($this->_skipCountP > 0){
						$this->_skipCountP--;
						return null;
					}
					if (is_array($nodeArray['w:r'])){
						foreach ($nodeArray['w:r'] as $i => $row){
							if (isset($row['w:tab'])){
								$text .= self::$_tabPlaceholder;
							}
							
							if (isset($row['w:t'][0]['#text'])){
								$text .= $row['w:t'][0]['#text'];
							} else {
								if (isset($row['w:t'])){
									if (!is_array($row['w:t'])){
										$text .= $row['w:t'];
									}
								}
							}
						}
					}
				}
				
				$text = $this->_parseText($text);
				
				$style = '';
				if (isset($nodeArray['w:pPr'])){
					$nodeStyle = $nodeArray['w:pPr'];
					if (is_array($nodeStyle)){
						
						if (isset($nodeStyle[0]['w:pStyle'][0]['w:val'])){
							$style = $nodeStyle[0]['w:pStyle'][0]['w:val'];
						}
						
						if (isset($nodeStyle[0]['w:numPr'][0])){
							# No indentation is 0 (eg. <ul><li>list item</li></ul> - the list item in this case would be 0 indent)
							if (isset($nodeStyle[0]['w:numPr'][0]['w:ilvl'][0]['w:val']))
								$indent = $nodeStyle[0]['w:numPr'][0]['w:ilvl'][0]['w:val'] ;
							else
								$indent = 0;
							
							$parsedNode = array(
								'type' => 'list_item',
								'style'=> $style,
								'text' => $text,
								'indent' => $indent
							);
							
							return $parsedNode;
						} 
					}
				}
				
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
								
								foreach ($tableCell['w:p'][0]['w:r'] as $iii => $tableCellRow){
									$this->_skipCountP++;
									
									if (isset($tableCellRow['w:t'][0]['#text'])){
										$cellText .= $tableCellRow['w:t'][0]['#text'];
									}
								}
							}
							
							$cellText = $this->_parseText($cellText);
							
							$row[$counter][] = array(
								'text' => $this->_parseInlineLists($cellText),
								'colspan' => 1,
							);
						}
					} else {
						$this->_skipCountP += $columnCount;
						
						# Row has single col
						$row[$counter]['colspan'] = $rowCount;
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
			
			$text = htmlentities($text, ENT_QUOTES, $this->encodingCaps);
			$text = str_replace(self::$_tabPlaceholder, "<span class=\"tab_placeholder\"></span>", $text);
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
					$processedText = '<ul>';
				}
				$processedText .= '<li>' . substr($text, 6) . '</li>';
			} elseif ($count > 1) {
				$processedText = '<ul>';
				foreach ($textChunks as $i => $listItem){
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