<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 18:49
 */
namespace PhilGale92Docx\Nodes;
use PhilGale92Docx\Docx;

/**
 * Class Node
 * @package PhilGale92Docx
 */
abstract class Node {

    const LIST_TYPE_BULLET = 'bulletlist';
    const LIST_TYPE_NUMBER = 'numberedlist';
    const LIST_TYPE_LETTER = 'alphalist';

    /**
     * @var null | \DOMElement
     */
    protected $_domElement = null;
    /**
     * @var null | \PhilGale92Docx\Docx
     */
   protected $_docx = null;

    /**
     * @var int
     */
   protected $_indent = 0;
    /**
     * @var int
     */
   protected $_listLevel = 0;
    /**
     * @var self::LIST_TYPE_BULLET | self::LIST_TYPE_NUMBER | self::LIST_TYPE_LETTER
     */
   protected $_listType = self::LIST_TYPE_BULLET;

    /**
     * @var string
     */
   protected $_prependOutput = '';
    /**
     * @var string
     */
   protected $_appendOutput = '';

    /**
     * @var null | \PhilGale92Docx\Style
     * @desc Tracks the discovered word style of the given node
     */
    protected $_wordStyle = null;

    /**
     * @var Run[]
     * @desc Track internal run objects
     */
    protected $_run = [] ;

    /**
     * @var string
     */
    protected $_type = '';

    /**
     * Node constructor.
     * @param $docx \PhilGale92Docx\Docx
     * @param $domElement  \DOMElement | null
     */
   final public function __construct($docx, $domElement){
       $this->_docx = $docx;
       $this->_domElement = $domElement;
       $this->_wordStyle = $this->_getStyle( $this->_domElement ) ;

       $this->_extender( $docx );

       if ($domElement == null ) {
           $this->_type = 'undefined';
       } else {
           $this->_type = $domElement->nodeName;
       }
   }

    /**
     * @param \PhilGale92Docx\Docx $docx
     * @stub
     */
   protected function _extender( $docx ){

   }

    /**
     * @param string $renderMode
     * @return string
     */
   protected function _getProcessedTextFromRun( $renderMode = Docx::RENDER_MODE_HTML){
       $ret = '';
       foreach ($this->_run as $run){
           /**
            * @var $run Run
            */
           $ret .= $run->getProcessedText( $renderMode );
       }
       return $ret ;
   }

    /**
     * @return string
    */
   protected function _getRawTextFromRun(){
       $ret = '';
        foreach ($this->_run as $run){
            /**
             * @var $run Run
             */
            $ret .= $run->getRawText();
        }
        return $ret ;
   }

    /**
     * @param string $renderMode
     * @return string
     */
   public function render($renderMode = Docx::RENDER_MODE_HTML){
        $ret = $this->_prependOutput;
        $elementPrepend = $elementAppend = $idAttr = '';

        if ($renderMode == Docx::RENDER_MODE_HTML) {
            if ($this->_type == 'w:p') {
                if (is_object($this->_wordStyle)) {
                    if ($this->_wordStyle->getFlagGenerateHtmlId()) {
                        # Compile the text from the runarr without the prepend / appending
                        $rawStr = $this->_getRawTextFromRun();

                        # Construct an htmlId, then use the styleData to decide what to do with it
                        $htmlId = self::_getHtmlIdFromString($rawStr);
                        $idAttr = ' id="' . $htmlId . '"';

                    }

                    $classStr = '';
                    if ($this->_wordStyle->getHtmlClass() != '')
                        $classStr = ' class="' . $this->_wordStyle->getHtmlClass() . '"';

                    $elementPrepend .= '<' . $this->_wordStyle->getHtmlTag() . $classStr . $idAttr . '>';
                    $elementAppend .= '</' . $this->_wordStyle->getHtmlTag() . '>';
                } else {
                    $elementPrepend .= '<p>';
                    $elementAppend .= '</p>';
                }
            }
        }

        $ret .= $elementPrepend;

        /*
         * Apply node-level indent
         */
       if ($renderMode == Docx::RENDER_MODE_HTML) {
           if ($this->_indent != 0)
               $ret .= '<span class="indent ind_' . $this->_indent . '">&nbsp;</span>';
       }
       /*
        * Run table sys. injection
        */
       if ($this->_type == 'w:tbl'){
           /**
            * @var $this Table
            */
           $ret .= $this->getTableRender($renderMode );
       }

       /*
        * Load up actual processed contents !
        */
       $ret .= $this->_getProcessedTextFromRun( $renderMode );

       /*
        * Apply appends
        */
        $ret .= $elementAppend;

        $ret .= $this->_appendOutput;

        return $ret ;


   }

    /**
     * @desc Integrates this Node object, into the Docx
     * @param $docx \PhilGale92Docx\Docx
     * @param bool $bRunAttachmentToRoot
     */
   public function attachToDocx($docx, $bRunAttachmentToRoot = true ){
       unset ( $this->_docx);
       foreach ($this->_run as $run){
           $run->unsetHelpers();

       }
       if ($bRunAttachmentToRoot) {
           $docx->attachNode($this);
       }
   }


    /**
     * @param null DomElement $domElement
     * @return \PhilGale92Docx\Style
     */
   protected function _getStyle($domElement = null ) {
       if ($domElement == null ) $domElement = $this->_domElement;
       $styleQuery = $this->_docx->getXPath()->query("w:pPr/w:pStyle", $domElement);
       $style = '';
       if ($styleQuery->length != 0)
           $style = $styleQuery->item(0)->getAttribute('w:val');

       return \PhilGale92Docx\Style::getFromStyleName($style, $this->_docx ) ;
   }

    /**
     * @return int
     * @desc Exposes list level for the list-post processing stage
     */
   public function getListLevel(){
       return $this->_listLevel;
   }

    /**
     * @return \PhilGale92Docx\Style|null
     */
   public function getStyle(){
       return $this->_wordStyle;
   }

    /**
     * @param $typeString string
     * @desc Allows you to override $this->_type
     */
   public function setType($typeString){
       $this->_type = $typeString;
   }

    /**
     * @param $additionalString string
     * @desc Exposes ->prepend, but adds to it
     */
   public function prependAdditional($additionalString){
       $this->_prependOutput = $additionalString . $this->_prependOutput;
   }

    /**
     * @param $additionalString string
     * @desc Exposes ->append, but adds to it
     */
   public function appendAdditional($additionalString){
       $this->_appendOutput .= $additionalString;
   }


    /**
     * @desc Converts internal docx measurment into px
     * @param $twip int
     * @return int
     */
    protected function _twipToPt($twip){
        $px = round(intval($twip) / 20);
        return $px;
    }


    /**
     * @param $rawString string
     * @desc Given a string, we process out any characters that cannot be output for an htmlId attribute
     * @return string
     */
    protected static function _getHtmlIdFromString($rawString){
        $ret = 'docx_' . $rawString;
        $ret = str_replace(['&nbsp;', " "], ["", '_'], $ret);
        $ret = trim(strip_tags($ret));
        $ret = preg_replace("/[^A-Za-z0-9_]/", '', $ret);
        return $ret;
    }

}
