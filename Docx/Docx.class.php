<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 16:47
 */
namespace Docx ;
use Docx\Nodes\Para;

/**
 * Class Docx
 * @desc Prepares xPath & domDocument for loaded .docx file,
 * and processes elements into internal Node & Run objects
 * @package Docx
 */
class Docx extends DocxFileManipulation {
    /**
     * @var null  | \DOMXPath
     */
    protected $_xPath = null ;
    /**
     * @var int
     * @deprecated
     * @desc -1 index, so when looping starts, it goes from 0
     */
    private $_incrementedInternalNodeId = -1 ;
    /**
     * @var Nodes\Node[]
     * @desc Track constructed Nodes
     */
    protected $_constructedNodes = [];

    /**
     * Docx constructor.
     * @param $fileUri
     */
    public function __construct($fileUri){
        parent::__construct($fileUri);
        try {
            $this->_loadNodes();
        } catch (\Exception $e) {
            var_dump($e);
            die;
        }
    }



    /**
     * @desc Pull out the primary data containers ( nodes ) that have different types depending on content type
     * @throws \Exception
     */
    private function _loadNodes(){
        /*
         * Prepare the DomDocument
         */
        $dom = new \DOMDocument();
        $dom->loadXML($this->_xmlStructure, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
        $dom->encoding = 'utf-8';

        /*
         * Set up xPath for improved dom navigating
         */
        $xPath = new \DOMXPath($dom);
        $xPath->registerNamespace('mc', "http://schemas.openxmlformats.org/markup-compatibility/2006");
        $xPath->registerNamespace('wp', "http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing");
        $xPath->registerNamespace('w', "http://schemas.openxmlformats.org/wordprocessingml/2006/main");
        $xPath->registerNamespace('a', "http://schemas.openxmlformats.org/drawingml/2006/main");
        $xPath->registerNamespace('pic', "http://schemas.openxmlformats.org/drawingml/2006/picture");
        $xPath->registerNamespace('v', "urn:schemas-microsoft-com:vml");
        $this->_xPath = $xPath;

        /*
         * Now we need to load up the root node, and then iterate through recursively
         */
        $bodyElementResult = $xPath->query('//w:body'); // //w:drawing | //w:txbxContent | //w:tbl | //w:p'
        if ($bodyElementResult->length > 0 ) {
            $bodyElement = $bodyElementResult->item(0 ) ;
            $this->loadNodesFromElement($bodyElement) ;
        } else {
            throw new \Exception('No Body element found');
        }


    }

    /**
     * @param $domElement \DOMElement
     */
    public function loadNodesFromElement($domElement){
        foreach ($domElement->childNodes as $childNode){
            /**
             * @var $childNode \DOMElement
             */
            switch ($childNode->tagName){
                case 'w:p':

                    $node = new Para($this, $childNode);
                    $node->attachToDocx($this);

                    break;
            }
        }
    }
    /**
     * @desc Attaches a given Node to $this
     * @param $nodeObj Nodes\Node
     */
    public function attachNode($nodeObj){
        $this->_constructedNodes[] = $nodeObj;
    }


    /**
     * @param string $renderViewType
     * @return string
     */
    public function render($renderViewType = 'html'){
        $ret = '';
        foreach ($this->_constructedNodes as $constructedNode){
            $ret .=  $constructedNode->render($renderViewType);
        }
        return $ret ;

    }

    /**
     * @return \DOMXPath|null
     */
    public function getXPath(){
        return $this->_xPath;
    }


    /**
     * @param $rawString string
     * @desc Given a string, we process out any characters that cannot be output for an htmlId attribute
     * @return string
     */
    public static function getHtmlIdFromString($rawString){
        $ret = 'docx_' . $rawString;
        $ret = str_replace(['&nbsp;', " "], ["", '_'], $ret);
        $ret = trim(strip_tags($ret));
        $ret = preg_replace("/[^A-Za-z0-9_]/", '', $ret);
        return $ret;
    }


    /**
     * @param string | null $linkupId
     * @return LinkAttachment[] | LinkAttachment
     */
    public function getAttachedLinks($linkupId = null){
        if ($linkupId == null ) {
            return $this->_linkAttachments;
        }
        $ret = [];
        foreach ($this->_linkAttachments as $linkAttachment ){
            if ($linkAttachment->getLinkupId() == $linkupId) {
                $ret = $linkAttachment;
            }
        }
        return $ret ;
    }

    /**
     * @param string | null $imageLinkupId
     * @return FileAttachment[] | FileAttachment
     */
    public function getAttachedFiles($imageLinkupId = null){
        if ($imageLinkupId == null) {
            return $this->_fileAttachments;
        }
        $ret = [] ;
        foreach ($this->_fileAttachments as $fileAttachment) {
            if ($fileAttachment->getLinkupId() == $imageLinkupId){
                $ret = $fileAttachment;
            }
        }
        return $ret;
    }

    /**
     * @desc Converts internal docx measurment into px
     * @param $twip int
     * @return int
     */
    public function twipToPt($twip){
        $px = round($twip / 20);
        return $px;
    }


    /**
     * @desc Generates a unique ID for a given node ( on request )
     * @deprecated
     * @return int
     */
    public function generateNodeId(){
        $this->_incrementedInternalNodeId++;
        return $this->_incrementedInternalNodeId ;
    }
}