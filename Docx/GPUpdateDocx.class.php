<?php 
	namespace Docx {
		/**
		 * @name GPUpdateDocx
		 * @desc Customised renderer & storage prep
		 * @author Phil Gale
		 *
		 */
		class GPUpdateDocx extends Docx {
			public static $hardCodeArr = array(
				'take home message',
				'bright ideas',
				'online resources',
				'personal learning'
			);
			
			public $brightIdeaTxt = '';
			public $takeHomeTxt = '';
			public $onlineResourceTxt = '';
			public $personalLearningTxt = '';
			
			public $topicName = '';
			
			protected $brightIdeaOpen = false;
			protected $takeHomeOpen = false;
			protected $onlineResourcesOpen = false;
			protected $personalLearningOpen = false;
			
			
			# Customise the storage to ignore the CTA strings
			public static function prepStorage($node, $styleData, $str){
				if ($node->type != 'listitem'){					
					$str = strip_tags($str);
					
					if (in_array(strtolower($str), self::$hardCodeArr)){
						return '';
					}
					
					$id = Node::buildHtmlId($str, $styleData);
					$str = '<a class="' . self::$storageLinkClass . '" href="#' . $id . '">' . $str . '</a>';
					return $str;
				} else return '';
			}
			
			/**
			 * @name render
			 * @desc Turns the html array into a string ready to output
			 */
			public function render(){
				$html = '';
				$topicHeaderCount = -1;
				foreach ($this->nodes as $node){
					if ($node->wordStyle == '1TopicHeading'){
						$topicHeaderCount++;
					}
				}
				
				foreach ($this->nodes as $node){
					$storedHtml = '';
					# Render Stored elements (if any)
					$styleData = Style::getStyleObject($node->wordStyle, $this);
					if (is_object($styleData)){
						if (isset($this->_passUnderStorage[$styleData->htmlClass])){
							if (isset($this->_passUnderStorage[$styleData->htmlClass][$this->_currentPassUnderKey])){
								if ($topicHeaderCount != 0){ $topicHeaderCount--; } else {
									$res = $this->_passUnderStorage[$styleData->htmlClass][$this->_currentPassUnderKey];
									foreach ($res as $subhtml){
										$storedHtml .= $subhtml;
									}
									unset($this->_passUnderStorage[$styleData->htmlClass][$this->_currentPassUnderKey]);
								}
							}	
						}
					}
					
					# Case to remove empty <li>'s 
					$node->htmlProcess = str_replace(array("<li></li>", "<p></p>"), "", $node->htmlProcess);
					
					# Process the CTA data
					$rawStr = strtolower(strip_tags($node->htmlProcess));
					if (in_array($rawStr, self::$hardCodeArr)){
						$this->takeHomeOpen = false;
						$this->brightIdeaOpen = false;
						$this->onlineResourcesOpen = false;
						$this->personalLearningOpen = false;
						
						if ($rawStr == 'take home message')
							$this->takeHomeOpen = true;
						
						if ($rawStr == 'bright ideas')
							$this->brightIdeaOpen = true;
						
			#			if ($rawStr == 'online resources')
			#				$this->onlineResourcesOpen = true;
						
						if ($rawStr == 'personal learning'){
							$this->personalLearningOpen = true;
							$this->personalLearningTxt = 'ent';
						}
						
					}
					
					$str = $node->htmlProcess . $storedHtml;
					
					$emptyFlag = false;
					if ($this->brightIdeaOpen){
						$this->brightIdeaTxt .= $str;
					
						$emptyFlag = true;
					}
					if ($this->takeHomeOpen){
						$this->takeHomeTxt .=  $str;
						$emptyFlag = true;
					}
					if ($this->onlineResourcesOpen){
						$this->onlineResourceTxt .=  $str;
						$emptyFlag = true;
					}
					if ($this->personalLearningOpen){
						$this->personalLearningOpen .=  $str;
						$emptyFlag = true;
					}
					
					if (isset($styleData->htmlClass)){
						if ($styleData->htmlClass == 'topic_heading' && $this->topicName == ''){
							$this->topicName =  strip_tags($node->htmlProcess);
						} elseif ($styleData->htmlClass == 'topic_heading'){
							$str = str_replace('class="topic_heading"', 'class="topic_heading is_referenceline"', $str);
						}
					}
					
					if ($emptyFlag) $str = '';
					$html .= $str;
					
				}
				$this->html = $html;
			}
			
			
		}
	}