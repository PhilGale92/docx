<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 16:47
 */
namespace PhilGale92Docx ;
use PhilGale92Docx\Nodes\Node;
use PhilGale92Docx\Nodes\Para;
use PhilGale92Docx\Nodes\Table;

/**
 * Class Docx
 * @desc Prepares xPath & domDocument for loaded .docx file,
 * and processes elements into internal Node & Run objects
 * @package PhilGale92Docx
 */
class Docx extends DocxFileManipulation {
    /**
     * @desc Use 'html' to set the full render mode as html
     */
    const RENDER_MODE_HTML = 'html';
    /**
     * @desc Use 'plain' to avoid using any html specific classes / attributes
     */
    const RENDER_MODE_PLAIN = 'plain';

    /**
     * @var DocxMetaDataAttribute[]
     */
    protected $_docxMetaData = [];
    /**
     * @var null  | \DOMXPath
     */
    protected $_xPath = null ;

    /**
     * @var Nodes\Node[]
     * @desc Track constructed Nodes
     */
    protected $_constructedNodes = [];

    /**
     * @desc Parses out internal node objects from the loaded XML structs
     */
    public function parse(){
        parent::parse();
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
            $this->loadNodesFromElement($bodyElement, true ) ;
        } else {
            throw new \Exception('No Body element found');
        }


    }

    /**
     * @param $domElement \DOMElement
     * @param bool $bIsFromRootElement
     *  If set to TRUE, elements are automatically tagged to parent Docx object,
     *  its set to false if the relevant Node is self-handling its elements
     * @return Node[]
     */
    public function loadNodesFromElement($domElement, $bIsFromRootElement = false ){
        $ret = [];
        foreach ($domElement->childNodes as $childNode){
            /**
             * @var $childNode \DOMElement
             */
            $node = null ;
            switch ($childNode->tagName){
                case 'w:tbl':
                    $node = new Table($this, $childNode);
                    break;
                case 'w:p':
                    $node = new Para($this, $childNode);
                    break;
            }
            if (is_object($node)) {
                $node->attachToDocx($this, $bIsFromRootElement );
                $ret[] = $node;
            }
        }

        $ret = $this->_parseMetaDataStylesPostProcessor($ret ) ;
        $ret  = $this->_listPostProcessor( $ret );
        $ret = $this->_boxWrapPostProcessor($ret ) ;


        /*
         * Ensure if we're working on the root level,
         * we properly propagate the new node structure up
         */
        if ($bIsFromRootElement) {
            $this->_constructedNodes = $ret ;
        }

        return $ret ;
    }

    /**
     * @desc Pull out any styles that are flagged as system, so we can populate
     * the _systemContents arr
     * @param $nodeArr Node[]
     * @return Node[]
     */
    protected function _parseMetaDataStylesPostProcessor( $nodeArr ){
        $bHasUnset = false ;
        foreach ($nodeArr as $i => $node){
            $style = $node->getStyle() ;
            if ($style->getIsMetaData()){
                $this->_docxMetaData[] = new DocxMetaDataAttribute(
                    $style->getStyleId(),
                    $node,
                    $node->render($style->getMetaDataRenderMode())
                );
                $bHasUnset =  true ;
                unset($nodeArr[ $i ]);
            }
        }

        /*
         * As we are removing parts of the nodeArr, we need to reset the array keys
         * otherwise the append/prepend injection logic in other processors will hit
         * empty lines when rewinding, and break
         */
        if ($bHasUnset){
            $nodeArr = array_values($nodeArr ) ;
        }

        return $nodeArr;
    }

    /**
     * @desc Modifies passed nodes, and finds the relevant list tags
     * and modifies the sibling nodes prepend/append attributes as needed
     * @param $nodeArr Node[]
     * @return Node[]
     */
    protected function _listPostProcessor($nodeArr ){
        $prevListLevel = 0;

        /*
         * Check the last item if it has an indent level we need to ensure another iteration occurs !
         */
        if (!empty($nodeArr)) {
            $nodeEnd = end($nodeArr);
            if ($nodeEnd->getListLevel() > 0 ) {
                $fauxItem = new Nodes\FauxList(
                    $this, null
                );

                $nodeArr[] = $fauxItem ;
            }
        }

        foreach ($nodeArr as $i =>  $node ) {
            $currentListTag = 'ul';
            /*
             * Override the node type
             */
            if ($node->getListLevel() > 0) {
                $node->setType('listitem');
            }
            /*
             * Get class attribute (if any)
             */
            $classInject = '';
            if (is_object($node->getStyle())){
                $styleData = $node->getStyle();
                if ($node->getListLevel() > 0) {
                    if ($styleData->getListHtmlTag() !== null ) {
                        $currentListTag = $styleData->getListHtmlTag();
                    }
                }
                if ($styleData->getHtmlClass() != '')
                    $classInject = $styleData->getHtmlClass() . '"';
            }


            $liClassStr = '';
            if ($classInject != ''){
                $liClassStr = ' class="' . $classInject . '"';
            }

            /*
             * List tag calculations
             */
            if ($prevListLevel > $node->getListLevel()){
                for ($loopI = $prevListLevel; $loopI > $node->getListLevel(); $loopI--){
                    $last = array_pop($listTagStack);

                    $nodeArr[$i - 1]->appendAdditional('</li></' .  $last . '>');
                }
            } else {
                if ($prevListLevel > 0 && $prevListLevel == $node->getListLevel()) {
                    $nodeArr[$i - 1]->appendAdditional('</li>');
                }
            }
            if ($prevListLevel < $node->getListLevel()){
                for ($loopI = $prevListLevel; $loopI < $node->getListLevel(); $loopI++){
                    $listTagStack[] = $currentListTag ;
                    $node->prependAdditional('<' . $currentListTag . '><li' . $liClassStr . '>');
                }
            } else {
                if ($node->getListLevel() > 0  ) {
                    $node->prependAdditional('<li' . $liClassStr . '>');
                }
            }
            $prevListLevel = $node->getListLevel();
        }

        return $nodeArr;
    }


    /**
     * @desc Wrap any nodes that are siblings with the same box Style attributes
     * @param $nodeArr Node[]
     * @return Node[]
     */
    protected function _boxWrapPostProcessor( $nodeArr ) {
        $prevStyleBoxName = null;
        $prevBoxIsOpen = false ;
        foreach ($nodeArr as $i => $node) {
            $style = $node->getStyle();

            $currentBoxIsOpen = $style->getBoxSimilarSiblings();
            $currentStyleBoxName = $style->getBoxClassName();

            if ($currentBoxIsOpen && $prevBoxIsOpen && $prevStyleBoxName != $currentStyleBoxName){
                $nodeArr[$i - 1]->appendAdditional('</div>');
                /*
                 * Now we've injected the box closure due to different style names
                 * lets mark the flag as such, so if this current node should be wrapped
                 * then it knows what behaviour to do
                 */
                $prevBoxIsOpen = false;
            }

            if ($currentBoxIsOpen && !$prevBoxIsOpen){
                $node->prependAdditional('<div class="' . $currentStyleBoxName. '">');
            } else if ($prevBoxIsOpen && !$currentBoxIsOpen) {
                $nodeArr[$i - 1]->appendAdditional('</div>');

            }

            $prevStyleBoxName = $currentStyleBoxName;
            $prevBoxIsOpen = $currentBoxIsOpen;
        }

        return $nodeArr ;
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
    public function render($renderViewType = self::RENDER_MODE_HTML){
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
                break ;
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
                break ;
            }
        }
        return $ret;
    }

    /**
     * @param $styleId string
     * @return Style
     */
    public function getDeclaredStyleFromId($styleId){
        $ret = null;
        foreach ($this->_declaredStyles as $style ) {
            if ($style->getStyleId() == $styleId){
                $ret =  $style;
                break;
            }
        }
        if ($ret == null) $ret = new Style();
        return $ret ;
    }


    /**
     * @param $style Style
     * @desc Adds a Style object to docx,
     * so we can customise html output depending on style templates
     * @return $this
     */
    public function addStyle($style){
        $this->_declaredStyles[] = $style;
        return $this;
    }

    /**
     * @param string | null $styleId ( Defaults to NULL )
     * @desc Gets the system internal contents, that are populated from any styles where
     *  ->setIsSystemStyle(true) is used
     * @return DocxMetaDataAttribute[]
     */
    public function getMetaData($styleId = null ){
        if ($styleId == null ) {
            return $this->_docxMetaData;
        }
        $ret = [ ];
        foreach ($this->_docxMetaData as $attribute ) {
            if ($attribute->getStyleId() == $styleId) $ret[] = $attribute;
        }
        return $ret;
    }

    /**
     * @desc Gets a list of all detected missing styles,
     * pass FALSE to get all
     * @param $bOnlyMissing bool
     * @return string[]
     */
    public function getDetectedStyles($bOnlyMissing = true ){
        if ( ! $bOnlyMissing) return $this->_detectedStyles;

        $ret = $this->_detectedStyles;
        $declaredStyles = [];
        foreach ($this->_declaredStyles as $style){
            $declaredStyles[] = $style->getStyleId();
        }
        foreach ($ret as $i => $detectedStyle){
            if (in_array($detectedStyle, $declaredStyles)) unset($ret[$i]);
        }
        return $ret ;
    }
}