<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 16:47
 */
namespace Docx ;
use Docx\Nodes\Para;

class Docx extends DocxFileManipulation {
    /**
     * @var null  | \DOMXPath
     */
    protected $_xPath = null ;
    /**
     * @var int
     * @desc -1 index, so when looping starts, it goes from 0
     */
    private $_incrementedInternalNodeId = -1 ;
    /**
     * @var array
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
     * @desc Generates a unique ID for a given node ( on request )
     * @return int
     */
    public function generateNodeId(){
        $this->_incrementedInternalNodeId++;
        return $this->_incrementedInternalNodeId ;
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


        var_dump($this);

        die;
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
     * @return \DOMXPath|null
     */
    public function getXPath(){
        return $this->_xPath;
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
     * @desc Attaches a given Node to $this
     * @param $nodeObj Nodes\Node
     */
    public function attachNode($nodeObj){
        $this->_constructedNodes[] = $nodeObj;
    }

    /**
     * @param string $renderViewType
     * @return $this
     */
    public function render($renderViewType = 'html'){

        return $this;

    }
}