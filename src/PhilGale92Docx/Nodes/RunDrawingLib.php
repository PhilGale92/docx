<?php
/**
 * Created by PhpStorm.
 * User: Phil Gale
 * Date: 21/02/2019
 * Time: 14:59
 */

namespace PhilGale92Docx\Nodes ;
/**
 * Class RunDrawingLib
 * @package PhilGale92Docx\Nodes
 * @desc Library for Run handlers for drawing (img) extraction from xml
 */
abstract class RunDrawingLib {
    /**
     * @var null | \PhilGale92Docx\Docx
     *
     */
    protected $_docx = null;
    /**
     * @var null | \DOMElement
     */
    protected $_runElementNode = null;

    /**
     * @return \PhilGale92Docx\FileAttachment|null
     */
    protected function _loadDrawingData(){
        /*
         * Query 'blip' for the image relation id, that allows us to get the FileAttachment object
         */
        $blipQuery = $this->_docx->getXPath()->query(".//pic:blipFill/a:blip", $this->_runElementNode);
        if ($blipQuery->length == 0) return null;

        # Get the imageToUseId by searching the blip node for an id
        $imageToUseId = null;
        foreach ($blipQuery->item(0)->attributes as $blipEmbedNode) {
            if ($blipEmbedNode->nodeName == 'r:embed') {
                $imageToUseId = $blipEmbedNode->nodeValue;
                break;
            }
        }
        if ($imageToUseId == null) return null ;

        # Use the id as a key within the _images array
        $imageData = $this->_docx->getAttachedFiles($imageToUseId);

        if (!is_object($imageData)) return null;

        # Defaults are initially set as 'auto'
        $w = $imageData->getWidth();
        $h = $imageData->getHeight();

        /*
         * Query for width/height override attributes (if any)
         */
        $extentQuery = $this->_docx->getXPath()->query(".//wp:extent", $this->_runElementNode);
        if ($extentQuery->length > 0 ) {
            $cxVal = $cyVal = null ;
            foreach ($extentQuery->item(0)->attributes as $attribute){
                /**
                 * @var $attribute \DOMAttr
                 */
                if ($attribute->nodeName == 'cx')  {
                    $cxVal = $this->_emuToPx( $attribute->nodeValue ) ;
                }
                if ($attribute->nodeName == 'cy')  {
                    $cyVal = $this->_emuToPx( $attribute->nodeValue);
                }
            }

            if ($cxVal != null && $cxVal > 0 ) $w = $cxVal;
            if ($cyVal != null && $cyVal > 0 ) $h = $cyVal;
        }

        /*
         * Reapply the loaded dimensions back into the image object
         */
        $imageData->setWidth($w);
        $imageData->setHeight($h);


        /*
         * Query for crop info & apply to image
         */
        $cropQuery = $this->_docx->getXPath()->query(".//a:srcRect", $this->_runElementNode);
        if ($cropQuery->length > 0 ) {
            foreach ($cropQuery->item(0)->attributes as $attributeNode) {
                /**
                 * @var $attributeNode \DOMAttr
                 */
                $attrVal = (int) $attributeNode->nodeValue;

                if ($attributeNode->nodeName == 'b'){
                    $imageData->setCropBottom($attrVal);
                } else if ($attributeNode->nodeName == 't'){
                    $imageData->setCropTop($attrVal);
                } else if ($attributeNode->nodeName == 'l'){
                    $imageData->setCropLeft($attrVal);
                } else if ($attributeNode->nodeName == 'r'){
                    $imageData->setCropRight($attrVal);
                }
            }
        }

        return $imageData;
    }


    /**
     * @desc Converts internal docx measurement into px
     * @param $emu int
     * @return int
     */
    protected function _emuToPx($emu){
        $px = round($emu  / 9525 );
        return $px;
    }




}