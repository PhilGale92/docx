<?php
	namespace Docx {
		class Style {
			/**
			 * @name wordStyle
			 * @desc Name of the style within word 
			 * @type string
			 */
			public $wordStyle = '';
			/**
			 * @name htmlClass
			 * @desc An HTML class to be used for this element
			 * @type string
			 */
			public $htmlClass = '';
			/**
			 * @name htmlTag
			 * @desc HTML Tag (eg. 'p', 'h1', 'h*')
			 * @type string
			 */
			public $htmlTag = 'p';
			/**
			 * @name passUnderNextStyle
			 * @desc Tells the system to put a copy of this element into storage, to be displayed somewhere else (more documentation coming soon...ish)
			 * @type string
			 */
			public $passUnderNextStyle = '';
			/**
			 * @name addHtmlId
			 * @desc Tells the system to generate an HTML id for this element, by using its content + stripping out invalid charecters (alphanumeric + underscore)
			 * @type boolean
			 */
			public $addHtmlId = false;
			/**
			 * @name listLevel
			 * @desc Tells the system this element is an <li> and how deep it is. 0 = Not list, 1 = one level list, 2 = within a sublist
			 * @type numeric
			 */
			public $listLevel = 0;
			
			public function __construct($wordStyleName, $args = array()){
				$this->wordStyle = $wordStyleName;
				
				foreach ($args as $k => $v){
					$this->$k = $v;
				}
			}
			
			public static function getStyleObject($wordStyleName, $docx){
				if (isset($docx->styles[$wordStyleName])) return $docx->styles[$wordStyleName]; else return null;
			}
		}
	}