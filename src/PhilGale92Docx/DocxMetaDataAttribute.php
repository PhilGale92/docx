<?php
/**
 * Created by PhpStorm.
 * User: Phil Gale
 * Date: 28/02/2019
 * Time: 10:29
 */

namespace PhilGale92Docx;
use PhilGale92Docx\Nodes\Node;

/**
 * Class DocxMetaDataAttribute
 * @package PhilGale92Docx
 */
class DocxMetaDataAttribute {
    /**
     * @var string
     */
    protected $_baseStyleId = '';
    /**
     * @var null | Node
     */
    protected $_internalNode = null;
    /**
     * @var string
     */
    protected $_renderedContent = '';

    /**
     * DocxMetaDataAttribute constructor.
     * @param $styleId string
     * @param $internalNode Node
     * @param $contents string
     */
    public function __construct(
        $styleId,
        $internalNode,
        $contents
    )
    {
        $this->_baseStyleId = $styleId;
        $this->_internalNode = $internalNode;
        $this->_renderedContent = $contents ;
    }

    /**
     * @return Node
     */
    public function getNode(){
        return $this->_internalNode;
    }

    /**
     * @desc Pulls out the contents of the node, after it has been ran through its relevant render
     * @return string
     */
    public function getRenderedContent(){
        return $this->_renderedContent;
    }

    /**
     * @return string
     */
    public function getStyleId(){
        return $this->_baseStyleId;
    }

}