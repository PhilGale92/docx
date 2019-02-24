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
     * @var int|null
     */
    protected $id = null;

    /**
     * @var string|null
     * @deprecated
     */
    protected $parentId = null;

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
   protected $indent = 0;
    /**
     * @var int
     */
   protected $listLevel = 0;
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
     * @var null | int
     * @deprecated
     * @desc Internal NodeId of the parent table (if any )
     *
     */
    private $_tableId = null ;

    /**
     * @var Run[]
     * @desc Track internal run objects
     */
    protected $_run = [] ;

    /**
     * @var bool
     * @deprecated
     */
    protected $isDirect = false;

    /**
     * @var string
     */
    protected $type = '';

    /**
     * Node constructor.
     * @param $docx \Docx\Docx
     * @param $domElement \DOMElement
     * @param bool $isDirect ( Are we inside a table or similar? we may need to process differently if so)
     * @param string | null $parentNodeId ( Tracks our parent div)
     */
   final public function __construct($docx, $domElement,  $isDirect = false, $parentNodeId = null){
       $this->_docx = $docx;
       $this->_domElement = $domElement;
       $this->_wordStyle = $this->_getStyle( $this->_domElement ) ;

       $this->_extender( $docx, $isDirect );

       $this->id = $this->_docx->generateNodeId();
       $this->isDirect = $isDirect;
       $this->parentId = $parentNodeId;
       $this->type = $domElement->nodeName;
   }

    /**
     * @param \Docx\Docx $docx
     * @param bool $isDirect
     * @stub
     */
   protected function _extender( $docx, $isDirect ){

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

        if ($this->type == 'w:p'){
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
       if ($this->indent != 0)
           $ret .= '<span class="indent ind_' . $this->indent . '">&nbsp;</span>';

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
     */
   public function attachToDocx($docx){
       unset ( $this->_docx);
       $docx->attachNode($this ) ;
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

}