<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 16:47
 */
namespace Docx ;
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
        $this->_loadNodes();
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
         * Now we need to pull out the tags that are to be parsed and iterate through each one!
         */
        $elements = $xPath->query('//w:drawing | //w:txbxContent | //w:tbl | //w:p');
        foreach ($elements as $element ) {
            $parsedNode = new Node($this,  $element ) ;
            $parsedNode->attachToDocx($this);
        }
        var_dump($elements ) ;
        var_dump($this);
        die;
    }

    /**
     * @desc Attaches a given Node to $this
     * @param $nodeObj Node
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