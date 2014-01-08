<?php 
	namespace Docx {
		/**
		 * @name Node
		 * @desc Each Docx render is made up of a list of nodes
		 * @author Phil Gale
		 *
		 */
		class Node {
			
			public $id = null;
			public $dom = null;
			public $xPath = null;
			public $wordStyle = '';
			public $docx = null;
			public $type = '';
			public $parentId = null;
			public $listLevel = 0;
			public $run = array();
			public $indent = null;
			public $append = '';
			public $prepend = '';
			public $addEmptyTableCells = true;
			private $_tableId = 0;
			public static $counter = -1;
			
			/**
			 * @name _generateNodeId
			 * @desc Generates a unique id for a node (used within the table parser)
			 * @return number
			 */
			private static function _generateNodeId(){
				self::$counter++;
				return self::$counter;
			}
			
			
			/**
			 * @name buildHtmlId
			 * @desc Takes a node, and returns a potential htmlId
			 * @param string $string
			 * @param Style $wordStyleObject
			 * @return $string
			 */
			public static function buildHtmlId($string, $wordStyleObject){
				$string = 'docx_' . $string;
				$string = str_replace(" ", "_", $string);
				$string = trim(strip_tags($string));
				$string = preg_replace("/[^A-Za-z0-9_]/", '', $string);
				return $string;
			}
			
			/**
			 * @name __construct
			 * @desc Initalize an instance of Docx\Node
			 * @param DOMELEMENT $node
			 * @param Docx/Docx $docxObj
			 * @param boolean $isDirect (Defaults to FALSE)
			 * param boolean $addEmptyTableCells (Defaults to TRUE)
			 */
			public function __construct($node, $docxObj, $isDirect = false, $parentId = null, $addEmptyTableCells = true){
				$this->xPath = $docxObj->xPath;
				$this->docx = $docxObj;
				$this->dom = $node;
				$this->parseNode($isDirect);
				$this->id = $this->_generateNodeId();
				$this->isDirect = $isDirect;
				$this->parentId = $parentId;
				$this->type = $node->nodeName;
				$this->addEmptyTableCells = $addEmptyTableCells;
				
				$docxObj->attachNode($this);
			}
			
			/**
			 * @name _parseWrNode
			 * @desc Gets a list of run attributes for a WR node
			 * @param DOMELEMENT $wr
			 * @return $parsedWR array
			 */
			private function _parseWrNode($wr){
				$bold = false;
				$italic = false;
				$tab = false;
				$underline = false;
				$text = '';
				
				$runStyleQuery = $this->xPath->query("w:rPr", $wr);
				
				# Get inline Styles (italic / bold / underline)
				if ($runStyleQuery->length != 0){
					$runStyleNode = $runStyleQuery->item(0);
					foreach ($runStyleNode->childNodes as $styleSub){
						
						switch ($styleSub->nodeName){
							case 'w:i':
								$italic = true;
							break;
							case 'w:b':
								$bold = true;
							break;
							case 'w:u':
								$underAttr = $styleSub->getAttribute('w:val');
								if ($underAttr == 'single'){
									$underline = true;
								}
							break;
						}
					}
				}
				
				# Find if this run is a tab
				$tabQuery = $this->xPath->query("w:tab", $wr);
				if ($tabQuery->length == 1) $tab = true;
				
				# Get text & escape
				$textQuery = $this->xPath->query("w:t", $wr);
				foreach ($textQuery as $textRes){
					$text .= $textRes->nodeValue;
				}
				
				if ($text != '') $text = htmlEntitiesEncode($text);
				
				$parsedWR = array(
					'bold' => $bold,
					'italic' => $italic,
					'tab' => $tab,
					'underline' => $underline,
					'text' => $text
				);
				
				return $parsedWR;
			}
			
			/**
			 * @name parseNode
			 * @desc Processes the node into php data or html
			 * @param boolean $isDirect (Defaults to FALSE)
			 */
			public function parseNode($isDirect = false){
				$wordStyle = $this->findStyle($this->dom);
				$styleInfo = Style::getStyleObject($wordStyle, $this->docx);
				$this->wordStyle = $wordStyle;
				
				# Only proceed for nodes that are children of the w:body tag OR this method was called within a container
				if ($this->dom->parentNode->nodeName != 'w:body' && !$isDirect)
					return;
				
				if (!$isDirect) $this->_tableId = null;
				
				switch ($this->dom->nodeName){					
					case 'w:p':
						$isListItem = false;
						$listLevel = 0;
						$indent = null;
						
						# Get the list level using the openXml format
						$listQuery = $this->xPath->query("w:pPr/w:numPr/w:ilvl", $this->dom);
						if ($listQuery->length > 0){
							$listLevel = (int) $listQuery->item(0)->getAttribute('w:val') + 1;
						}
						
						# If the style list info is NOT 0, then override the openXml iteration
						if (is_object($styleInfo)){
							if ($styleInfo->listLevel > 0) $listLevel = $styleInfo->listLevel;
						}
						
						# Load hyperlink data (if any)
						$hyperQuery = $this->xPath->query("w:hyperlink", $this->dom);
						if ($hyperQuery->length > 0){
							$hyperlink = '';
							$hyperNode = $hyperQuery->item(0);
							foreach ($hyperNode->childNodes as $cn){
								if ($cn->nodeName == 'w:r')
									$hyperlink = $cn->nodeValue;
							}
							
							# If we have the raw hyperlink, parse it
							if ($hyperlink != ''){
								if (substr($hyperlink, 0, 4) != 'http') $modHyperlink = 'http://' . $hyperlink; else $modHyperlink = $hyperlink;
								
								$this->run[] = array(
									'text' => '<a href="' . $modHyperlink . '">' . $hyperlink . '</a>',
									'underline' => false,
									'tab' => false,
									'italic' => false,
									'bold' => false
								);
							}
						}
						
						# Join the different runs together
						$textRun = $this->xPath->query("w:r", $this->dom);
						$text = '';
						foreach ($textRun as $run){
							$wrArray = $this->_parseWrNode($run);
							$this->run[] = $wrArray;
						};
						
						# Get the indentation
						$indentQuery = $this->xPath->query("w:pPr/w:ind", $this->dom);
						if ($indentQuery->length > 0){
							$firstLineInd = $indentQuery->item(0)->getAttribute('w:firstLine');
							$indent = (int) Docx::twipToPt($firstLineInd);
						}
						
						$this->indent = $indent;
						$this->listLevel = $listLevel;
					break;
					case 'w:drawing':
						$this->img = $this->loadDrawing($this->dom);
					break;
					case 'w:txbxContent':
						
					break;
					case 'w:tbl':
						$this->_tableId = $this->id;
						$this->createTableGrid($this->dom);
					break;
				}
				
			}
			
			/**
			 * @name findStyle
			 * @desc Finds the word style of a given dom element
			 * @param DOMELEMENT $domNode
			 * @return string
			 */
			public function findStyle($domNode){
				$styleQuery = $this->xPath->query("w:pPr/w:pStyle", $domNode);
				$style = '';
				if ($styleQuery->length != 0)
					$style = $styleQuery->item(0)->getAttribute('w:val');
				
				return $style;
			}
			
			/**
			 * @name _renderCell
			 * @desc Creates a new instance of this class for all children of the current table cell, and inserts their output into the table
			 * @param DOMELEMENT $domNode (w:tc type)
			 * @return string
			 */
			private function _renderCell($cellDom){
				$html = '';
				foreach ($cellDom->childNodes as $cellChildNode){
					if ($cellChildNode->nodeName == 'w:p' || $cellChildNode->nodeName == 'w:drawing'){
						$subNode = new Node($cellChildNode, $this->docx, true, $this->_tableId);
						$html .= '{CELL_PLACEHOLDER_ID_' . $subNode->id . '}';
					}
				}
				return $html;
			}
			
			/**
			 * @name createTableGrid
			 * @desc Constructs an entire table using vertical merging + colspans
			 * @param DOMELEMENT $domNode
			 */
			public function createTableGrid($domNode){
				# Stage 1 - get the maxColumnCount using gridCol
				$maxColumnCount = $this->xPath->query("w:tblGrid/w:gridCol", $domNode)->length;
				
				# Stage 2 - Start a loop for each table row + cell to compile cell structure info
				$tableArr = array();
				$tableRowsDom = $this->xPath->query("w:tr", $domNode);
				foreach ($tableRowsDom as $i => $tableRowDom){
					$rowCellsDom = $this->xPath->query("w:tc", $tableRowDom);
					foreach ($rowCellsDom as $ii => $cellDom){
						# Vertical Merge
						if ($this->xPath->query("w:tcPr/w:vMerge", $cellDom)->length == 1)
							$cellVerticalMerge = true;
						else
							$cellVerticalMerge = false;
						
						# Cell Colspan
						$gridSpanDom = $this->xPath->query("w:tcPr/w:gridSpan", $cellDom);
						$colSpan = 1;
						if ($gridSpanDom->length != 0)
							$colSpan = (int) $gridSpanDom->item(0)->getAttribute('w:val');
						
						if (!isset($tableArr[$i])) $tableArr[$i] = array();
						
						$tableArr[$i][$ii] = array(
							'dom' => $cellDom,
							'verticalMerge' => $cellVerticalMerge,
							'colSpan' => $colSpan
						);
						
					}
				}
				
				# Stage 3 - Compile the vertical merge cells & apply the colspan variable to vertical merging
				$vMergeIndex = array();
				foreach ($tableArr as $i => $tableRow){
					foreach ($tableRow as $ii => $tableCell){
						$vMergeIndex[$i][$ii] = $tableCell['verticalMerge'];
						if ($tableCell['colSpan']){
							for ($loopI = $ii; $loopI < ($tableCell['colSpan'] - $ii); $loopI++){
								$vMergeIndex[$i][$loopI] = $tableCell['verticalMerge'];
							}
						}
					}
				}
				
				# Stage 4 - Compile the TRUE/FALSE declarations into a count of Td's
				# Create the required height caches, to help track vertical merging
				$mergeCache = array();
				for ($i = 0; $i < $maxColumnCount; $i++){
					$mergeCache[$i] = null;
					$heightCache[$i] = null;
				}
				
				$vMergeIndex = array_reverse($vMergeIndex, true);
				$verticalMergingColCounts = array();
				foreach ($vMergeIndex as $i => $tableRow){
					foreach ($tableRow as $ii => $tableCellIsVMerge){
						if ($tableCellIsVMerge){
							if ($mergeCache[$ii] != null) $mergeCache[$ii]++; else $mergeCache[$ii] = 1;
							if (!$vMergeIndex[$i - 1][$ii]) $verticalMergingColCounts[$i][$ii] = $mergeCache[$ii];
						} else {
							$mergeCache[$ii] = null;
							$verticalMergingColCounts[$i][$ii] = 1;
						}
					}
				}
				
				$verticalMergingColCounts = array_reverse($verticalMergingColCounts, true);
				
				# Stage 5 - Invert how the cell-heights are stored, due to how HTML handles tables differently then docx structures
				# - Html has small cells merged vertically, html has large cells with subcells
				$skipRow = 0;
				foreach ($verticalMergingColCounts as $i => &$row){
					$currentMaxHeight = 1;
					foreach ($row as $ii => $cellHeight){
						if ($cellHeight > $currentMaxHeight){
							$currentMaxHeight = $cellHeight;
						}
					}
									
					if ($skipRow > 0){ $skipRow--; unset($verticalMergingColCounts[$i]); continue; }
					if ($skipRow == 0)
						$skipRow = $currentMaxHeight - 1;
					foreach ($row as $ii => &$cellHeight){
						if ($heightCache[$ii] > 1){
							$heightCache[$ii]--;
						}
						
						if ($cellHeight == 1){
							$cellHeight = $currentMaxHeight;
						} else {
							$heightCache[$ii] = $cellHeight ;
							$cellHeight = 1;
						}
					}
					
					if ($this->addEmptyTableCells){
						for ($loopI = $ii; $loopI < $maxColumnCount; $loopI++)
							$row[$loopI] = 1;
					}

				}
				
				# Stage 6 - Compile the Td's into a single tabular array using the $verticalMergingColCounts
				$renderTable = array();
				foreach ($verticalMergingColCounts as $i => $row){
					$skipCount = -1;
					foreach ($row as $ii => $cellHeight){
						if ($skipCount > 0){
							$skipCount--;
							continue;
						}
						
						if ($cellHeight == 1) $renderTable[$i][$ii] = $tableArr[$i][$ii]; else {
							for ($cellInt = 1; $cellInt <= $cellHeight; $cellInt++){
								$renderTable[$i][$ii][] = $tableArr[$i + $cellInt - 1][$ii];
							}
						}
						
						# Skip colspan cells
						if ($tableArr[$i][$ii]['colSpan'] > 1){
							for ($loopI = $ii; $loopI < ($tableArr[$i][$ii]['colSpan'] - $ii); $loopI++){
								$skipCount++;
							}
						}

					}
				}
				
				# Stage 7 - Write the HTML (Tokenize where the contents of the table can be inserted)
				$html = '<table>';
				foreach ($renderTable as $i => $row){
					if ($i == 0) {
						$headerStr = ' class="headers"';
						$cellTag = 'th';
					} else {
						$headerStr = '';
						$cellTag = 'td';
					}
					
					$html .= '<tr' . $headerStr . '>';
					foreach ($row as $ii => $cell){
						
						$colSpanStr = '';
						if (isset($cell['colSpan'])){
							if ($cell['colSpan'] > 1){
								$colSpanStr = ' colspan="' . $cell['colSpan'] . '"';
							}
						}
						
						$subcellClassStr = '';
						if (isset($cell[0])) $subcellClassStr = ' has_subcell '; 
						
						$html .= '<' . $cellTag . ' class="col_' . ($ii + 1) . $subcellClassStr . '"' . $colSpanStr . '>';
						if (isset($cell[0])){
							# Sub cell within cell
							$html .= '<table>';
							$cellCount = count($cell);
							foreach ($cell as $iii => $subCell){
								# Dont render an empty subcell
								$contentCheck = $this->xPath->query("w:p/w:r", $subCell['dom']);
								if ($contentCheck->length == 0)
									continue;
								
								$html .= '<tr class="vmerge merge_' . $ii  . '_' . $iii . '"><td>';
								$html .= $this->_renderCell($subCell['dom']);
								$html .= '</td></tr>';
							}
							$html .= '</table>';
						} else {
							# Standard cell
							$html .= $this->_renderCell($cell['dom']);
						}
						$html .= '</' . $cellTag . '>';
					}
					$html .= '</tr>';
				}
				$html .= '</table>';
				$this->html = $html;
			}
			
			/**
			 * @name loadDrawing
			 * @desc Generates an image array (salvaged from previous version)
			 */
			public function loadDrawing(){
				# Get the blipFill for the imageRefId
				$mcAltContentXPath = $this->xPath->query("*/a:graphic/a:graphicData/pic:pic/pic:blipFill", $this->dom);
				$blipNode = $rectNode = null;
					
				foreach ($mcAltContentXPath as $blipFill){
					# The blip however is always required to get the imageRefId
					if ($blipFill->nodeName == null) continue;
					$blipNode = $blipFill;
				}
					
				# Get the prev. element to load the alterateContent block
				$prevElement = $this->dom->parentNode->previousSibling;
				if (!isset($prevElement->nodeName)) continue;
					
				# Load the alt Content for the dimensions
				$mcDimensionXPath = $this->xPath->query("mc:AlternateContent/mc:Fallback/w:pict/v:rect", $prevElement);
				foreach ($mcDimensionXPath as $dimensionWrapper){
					# If 'rect' is not found, we just use image width/height = auto so it is not required
					if ($dimensionWrapper->nodeName != null)
					$rectNode = $dimensionWrapper;
				}
					
				# Get the imageToUseId by searching the blip node for an id
				if ($blipNode != null){
					$blipQuery = $this->xPath->query("a:blip", $blipNode);
					foreach ($blipQuery as $blipRes){
						foreach ($blipRes->attributes as $blipEmbedNode){
							if ($blipEmbedNode->nodeName == 'r:embed'){
								$imageToUseId = $blipEmbedNode->nodeValue;
								break 2;
							}
						}
					}
				
					# Use the id as a key within the _images array
					$imageData = arrayComplexSearch($this->docx->images, 'id', $imageToUseId);
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
					
					# Collate the image into the parsed array
					return array(
						'type' => 'image',
						'name' => $imageData[0]['title'],
						'h' => $h,
						'w' => $w,
						'data' => $imageData[0]['data']
					);
				}
			}
		}
	}