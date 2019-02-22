<?php
/**
 * Created by PhpStorm.
 * User: Phil Gale
 * Date: 21/02/2019
 * Time: 14:59
 */

namespace Docx\Nodes ;
/**
 * Class RunDrawingLib
 * @package Docx\Nodes
 * @desc Library for Run handlers for drawing (img) extraction from xml
 */
abstract class RunDrawingLib {
    /**
     * @var null | \Docx\Docx
     *
     */
    protected $_docx = null;
    /**
     * @var Node
     */
    protected $_parentNode = null;
    /**
     * @var null | \DOMElement
     */
    protected $_runElementNode = null;

    /**
     * @return \Docx\FileAttachment|null
     */
    protected function _loadDrawingData(){
        # Get the blipFill for the imageRefId
        $mcAltContentXPath = $this->_docx->getXPath()->query("*/a:graphic/a:graphicData/pic:pic/pic:blipFill", $this->_runElementNode);
        $blipNode = $rectNode = null;

        foreach ($mcAltContentXPath as $blipFill){
            # The blip however is always required to get the imageRefId
            if ($blipFill->nodeName == null) continue;
            $blipNode = $blipFill;
        }

        # Get the prev. element to load the alternateContent block
        $prevElement = $this->_runElementNode->parentNode->previousSibling;
        if (!isset($prevElement->nodeName)) return null;

        # Load the alt Content for the dimensions
        $mcDimensionXPath = $this->_docx->getXPath()->query("mc:AlternateContent/mc:Fallback/w:pict/v:rect", $prevElement);
        foreach ($mcDimensionXPath as $dimensionWrapper){
            # If 'rect' is not found, we just use image width/height = auto so it is not required
            if ($dimensionWrapper->nodeName != null)
                $rectNode = $dimensionWrapper;
        }

        # Get the imageToUseId by searching the blip node for an id
        $imageToUseId = null;
        if ($blipNode != null) {
            $blipQuery = $this->_docx->getXPath()->query("a:blip", $blipNode);
            foreach ($blipQuery as $blipRes) {
                foreach ($blipRes->attributes as $blipEmbedNode) {
                    if ($blipEmbedNode->nodeName == 'r:embed') {
                        $imageToUseId = $blipEmbedNode->nodeValue;
                        break 2;
                    }
                }
            }
            if ($imageToUseId == null) return null ;

            # Use the id as a key within the _images array
            $imageData = $this->_docx->getAttachedFiles($imageToUseId);

            if (!is_object($imageData)) return null;

            # Defaults are initially set as 'auto'
            $w = $imageData->getWidth();
            $h = $imageData->getHeight();

            # Load the rect node if available to load the image dimensions
            if ($rectNode != null){
                $rectStyles = $rectNode->attributes;
                foreach ($rectStyles as $rectStyleNode){
                    if ($rectStyleNode->nodeName == 'style'){
                        $imageStyleArray = explode(";", $rectStyleNode->nodeValue);
                        foreach ($imageStyleArray as $imageStyle){
                            $styleInfo = explode(":", $imageStyle);
                            if (strtolower($styleInfo[0]) == 'width')
                                $w = $styleInfo[1];
                            if (strtolower($styleInfo[0]) == 'height')
                                $h = $styleInfo[1];
                        }
                        break;
                    }
                }
            }




            # Collate the image into the parsed array
            $imageData->setRunData([
                'type' => 'image',
                'name' => $imageData->getFileName(),
                'h' => $h,
                'w' => $w,
                'data' => $imageData->getFileData()
            ]);

            return $imageData;
        }
        return null ;
    }



}