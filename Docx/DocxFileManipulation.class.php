<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 16:48
 */
namespace Docx;
/**
 * Class DocxFileManipulation
 * @desc The purpose of this class, is pulling the xml structure out,
 * while preparing external references
 * @package Docx
 */
abstract class DocxFileManipulation {
    /**
     * @var string
     * @desc File path
     */
    private $_baseUri = '';
    /**
     * @var string
     * @desc RAW Docx XML
     */
    protected $_xmlStructure = '';
    /**
     * @var string
     * @desc Raw XML structure of relationships
     */
    protected $_xmlRelations = '';
    /**
     * @var FileAttachment[]
     * @desc Track files
     */
    protected $_fileAttachments = [] ;
    /**
     * @var array
     * @desc Track external reference based links
     */
    protected $_linkAttachments = [] ;

    /**
     * @var bool|null
     * @desc Track the previous entity_loader flag
     */
    private $_libXmlGlobalLoader = null ;

    /**
     * DocxFileManipulation constructor.
     * @param $fileUri
     */
    public function __construct($fileUri)
    {
        $this->_libXmlGlobalLoader  = libxml_disable_entity_loader( ) ;
        $this->_baseUri = $fileUri;
        $this->_extractArchives();
    }

    /**
     * @desc Reset entity_loader to previous value
     */
    public function __destruct()
    {
        libxml_disable_entity_loader($this->_libXmlGlobalLoader) ;
    }

    /**
     * @desc Unzip and track the useful files
     *  - We need to track relationships, the main structure and any image assets
     */
    private function _extractArchives(){
        $zipArchive = zip_open($this->_baseUri);
        while ( $zipEntry = zip_read($zipArchive)){
            $entryName = zip_entry_name($zipEntry);
            if (zip_entry_open($zipArchive, $zipEntry) == false) continue;

            if ($entryName == 'word/_rels/document.xml.rels'){
                $this->_xmlRelations = zip_entry_read($zipEntry, zip_entry_filesize($zipEntry));
            } else if ($entryName == 'word/document.xml'){
                $this->_xmlStructure = zip_entry_read($zipEntry, zip_entry_filesize($zipEntry));
            } else if (strpos($entryName, 'word/media') !== false ) {
                # Removes 'word/media' prefix
                $imageName = substr($entryName, 11);

                /*
                 * Prevent EMF file extensions passing,
                 * as they are used by word rather than being manually placed
                 * @TODO - would we ever want to display .emf? plausable to just always hide
                 * Need to look into the format details
                 */
                # if (!$this->_allowEmfImages){
                    if (substr($imageName, -3) == 'emf') continue;
                # }

                $imageData = base64_encode(
                    zip_entry_read($zipEntry, zip_entry_filesize( $zipEntry )  )
                );
                $this->_fileAttachments[$imageName] = new FileAttachment(
                    $imageName,
                    $imageData
                );
            }
            zip_entry_close($zipEntry) ;
        }
        zip_close($zipArchive ) ;
        $this->_processRelationships()  ;
    }

    /**
     * @desc Process the xmlRelations into link
     * mappings, and pull out any additional image data that is available !
     */
    private function _processRelationships(){
        if ($this->_xmlRelations != '') {
            $dom = new \DOMDocument();
            $dom->loadXML($this->_xmlRelations, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
            $dom->encoding = 'utf-8';
            $elements = $dom->getElementsByTagName('Relationship');
            foreach ($elements as $node) {
                $relationshipAttributes = $node->attributes;
                $relationId = $relationshipAttributes->item(0);
                $relationType = $relationshipAttributes->item(1);
                $relationTarget = $relationshipAttributes->item(2);
                /*
                 * Now split the links from image assets
                 */
                if (is_object($relationId) && is_object($relationTarget)){
                    $linkupId = $relationId->nodeValue;
                    if (stripos($relationType->nodeValue, 'relationships/hyperlink') !== false){
                        $this->_linkAttachments[$linkupId] = new LinkAttachment(
                            $linkupId,
                            $relationTarget->nodeValue
                        );
                    } else if (stripos($relationType->nodeValue, 'relationships/image') !== false) {
                        $imageName = substr($relationTarget->nodeValue, 6);
                        $this->_fileAttachments[$imageName]->setLinkupId($linkupId ) ;
                    }
                }
            }
        }
    }
}