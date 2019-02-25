<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 18:49
 */
namespace Docx\Nodes;
use Docx\Docx;

/**
 * Class Node
 * @package Docx
 */
abstract class Node {

    /**
     * @var null | \DOMElement
     */
    protected $_domElement = null;
    /**
     * @var null | \Docx\Docx
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
     * @var string
     */
   protected $_prependOutput = '';
    /**
     * @var string
     */
   protected $_appendOutput = '';

    /**
     * @var null | \Docx\Style
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
     * @param $docx \Docx\Docx
     * @param $domElement \DOMElement
     */
   final public function __construct($docx, $domElement){
       $this->_docx = $docx;
       $this->_domElement = $domElement;
       $this->_wordStyle = $this->_getStyle( $this->_domElement ) ;

       $this->_extender( $docx );
       $this->_type = $domElement->nodeName;
   }

    /**
     * @param \Docx\Docx $docx
     * @stub
     */
   protected function _extender( $docx ){

   }

    /**
     * @param string $renderMode
     * @return string
     */
   protected function _getProcessedTextFromRun( $renderMode = 'html'){
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
   public function render($renderMode = 'html'){
        $ret = $this->_prependOutput;
        $elementPrepend = $elementAppend = $idAttr = '';

        if ($this->_type == 'w:p'){
            if (is_object( $this->_wordStyle)){
                if ($this->_wordStyle->getFlagGenerateHtmlId()){
                    # Compile the text from the runarr without the prepend / appending
                    $rawStr = $this->_getRawTextFromRun();

                    # Construct an htmlId, then use the styleData to decide what to do with it
                    $htmlId = Docx::getHtmlIdFromString($rawStr);
                    $idAttr = ' id="' . $htmlId . '"';

                }

                $classStr = '';
                if ($this->_wordStyle->getHtmlClass() != '')
                    $classStr = ' class="' . $this->_wordStyle->getHtmlClass() . '"';

                $elementPrepend .= '<' . $this->_wordStyle->getHtmlTag() . $classStr . $idAttr .  '>';
                $elementAppend .= '</' . $this->_wordStyle->getHtmlTag() . '>';
            } else {
                $elementPrepend .= '<p>';
                $elementAppend .= '</p>';
            }
        }


        $ret .= $elementPrepend;

        /*
         * Apply node-level indent
         */
       if ($this->_indent != 0)
           $ret .= '<span class="indent ind_' . $this->_indent . '">&nbsp;</span>';

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
     * @param $docx \Docx\Docx
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
     * @return \Docx\Style
     */
   protected function _getStyle($domElement = null ) {
       if ($domElement == null ) $domElement = $this->_domElement;
       $styleQuery = $this->_docx->getXPath()->query("w:pPr/w:pStyle", $domElement);
       $style = '';
       if ($styleQuery->length != 0)
           $style = $styleQuery->item(0)->getAttribute('w:val');
       return \Docx\Style::getFromStyleName($style) ;
   }

    /**
     * @return int
     * @desc Exposes list level for the list-post processing stage
     */
   public function getListLevel(){
       return $this->_listLevel;
   }

    /**
     * @return \Docx\Style|null
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



}