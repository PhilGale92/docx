<?php
/**
 * Created by PhpStorm.
 * User: Phil Gale
 * Date: 21/02/2019
 * Time: 10:28
 */
namespace PhilGale92Docx\Nodes;
class Para extends Node {

    /**
     * @param \PhilGale92Docx\Docx $docx
     */
    protected function _extender( $docx ){

        $listLevel = 0;
        $indent = null;

        # Get the list level using the openXml format
        $listQuery = $this->_docx->getXPath()->query("w:pPr/w:numPr/w:ilvl", $this->_domElement);
        if ($listQuery->length > 0){
            $listLevel = (int) $listQuery->item(0)->getAttribute('w:val') + 1;
        }

        $numIdQuery = $this->_docx->getXPath()->query("w:pPr/w:numPr/w:numId", $this->_domElement);
        $listType = $this::LIST_TYPE_BULLET;

        if ($numIdQuery->length > 0 ){
            $numIdItem = $numIdQuery->item(0);
            foreach ($numIdItem->attributes as $attribute){
                /**
                 * @var $attribute \DOMAttr
                 */
                if ($attribute->nodeName == 'w:val'){
                    /*
                     * Found relevant attribute!
                     */
                    if (is_numeric($attribute->nodeValue)){
                        $listType = $this::LIST_TYPE_NUMBER;
                    } else {
                        $listType = $this::LIST_TYPE_LETTER;
                    }
                    break;
                }
            }
        }

        # If the style list info is NOT 0, then override the openXml iteration
        if ($this->_wordStyle->getListLevel() > 0 ) {
            $listLevel = $this->_wordStyle->getListLevel();
        }

        # Run through text runs & hyperlinks
        foreach ($this->_domElement->childNodes as $childNode){
            $run = new Run($docx, $childNode);
            if ($run->isValid()) $this->_run[] = $run;
        }

        # Get the indentation
        $indentQuery = $this->_docx->getXPath()->query("w:pPr/w:ind", $this->_domElement);
        if ($indentQuery->length > 0){
            $firstLineInd = $indentQuery->item(0)->getAttribute('w:firstLine');
            $indent = (int) $this->_twipToPt($firstLineInd);
        }

        $this->_indent = $indent;
        $this->_listLevel = $listLevel;
        $this->_listType = $listType;


    }


}