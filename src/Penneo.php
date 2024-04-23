<?php

namespace NPDigital\Api;

use Penneo\SDK\ApiConnector;
use Penneo\SDK\CaseFile;
use Penneo\SDK\CaseFileTemplate;
use Penneo\SDK\Folder;
use Penneo\SDK\Document;
use Penneo\SDK\Signer;
use Penneo\SDK\SignatureLine;
use Penneo\SDK\SigningRequest;
use Penneo\SDK\Exception;

class Penneo {

    protected $casefile, $document;
    public $response;

    function __construct()
    {
        $this->api = ApiConnector::initialize($_ENV['PENNEO_API_KEY'], $_ENV['PENNEO_API_SECRET'], 'https://'.$_ENV['PENNEO_API_URI']);
        $this->casefile = false;
        $this->document = false;
        $this->response = null;
    }

    static function casefile(
        Int $casefileId = 0
    ){
        $api = new Penneo();

        if ($casefileId > 0) {
            try {
                $api->casefile = CaseFile::find($casefileId);
            } catch (Exception $e) {
                $api->casefile = new CaseFile();
            }
        } else {
            $api->casefile = new CaseFile();
        }

        return $api;
    }

    static function document(
        Int $documentId
    ){
        $api = new Penneo();
        try {
            $api->document = Document::find($documentId);
        } catch (Exception $e) {
            $api->document = false;
        }

        return $api;
    }

    function download()
    {
        if ($this->document) {
            return $this->document->getPdf();
        }
        return false;
    }

    public function create(
        String $title = '',
        Array $documents,
        Array $signers,
        String $language = 'en',
        Object $template = null,
        Object $folder = null
    ){
        if ($this->casefile) {
            // Casefile meta data
            $this->casefile->setTitle($title);
            $this->casefile->setLanguage($language);

            CaseFile::persist($this->casefile);
            
            // Add template
            if (!is_null($template)) {
                $this->setTemplate($template);    
            }

            // Add to folder
            if (!is_null($folder)) {
                $this->setFolder($folder); 
            }

            // Purge documents and signers
            foreach($this->casefile->getDocuments() AS $document) {
                Document::delete($document);
            }
            foreach($this->casefile->getSigners() AS $signer) {
                Signer::delete($signer);
            }

            CaseFile::persist($this->casefile);

            // Add documents
            $this->addDocuments($documents);

            // Add signers
            $this->addSigners($signers);

            // Add signatures to all documents
            foreach($this->casefile->getDocuments() AS $document) {
                foreach($this->casefile->getSigners() AS $signer) {
                    $signature = new SignatureLine($document);
                    $signature->setRole('Signer');
                    SignatureLine::persist($signature);
                    $signature->setSigner($signer);
                }
            }

            return $this;
        }
    }

    public function delete() {
        CaseFile::delete($this->casefile);
    }

    public function send(){
        if ($this->casefile) {
            $this->casefile->send();
        }
        return $this;
    }

    public function addSigners(
        Array $signers
    ){
        foreach($signers AS $signer) {
            $this->addSigner(...$signer);
        }
        return $this;
    }

    public function addSigner(
        String $name,
        String $email,
        String $representing,
        Int $reminderInterval = 1, // days
        Int $signerTypeId = 0
    ){
        $signerTypes = $this->casefile->getSignerTypes();

        // Create signer
        $signer = new Signer($this->casefile);
        $signer->setName($name);
        $signer->setOnBehalfOf($representing);
        Signer::persist($signer);

        if (isset($signerTypes[$signerTypeId])) {
            $signer->addSignerType($signerTypes[$signerTypeId]);    
        }

        // Define signing request message
        $SigningRequest = $signer->getSigningRequest();
        $SigningRequest->setEmail($email);
        $SigningRequest->setEmailSubject('Document ready for signing: {{casefile.name}}');
        $SigningRequest->setEmailText('Dear {{recipient.name}},'.str_repeat(PHP_EOL,2).'Please read and sign the following document(s):'.str_repeat(PHP_EOL,2).'{{documents.list}}');
        // Define reminder message
        if ($reminderInterval) {
            $SigningRequest->setReminderInterval($reminderInterval);
            $SigningRequest->setReminderEmailSubject('We are missing your signature.');
            $SigningRequest->setReminderEmailText('Dear {{recipient.name}},'.str_repeat(PHP_EOL,2).'We are still missing your signature.');            
        }
        // Allow biometric signing
        $SigningRequest->setEnableInsecureSigning(true);
        SigningRequest::persist($SigningRequest);
    }

    public function getSigners() {
        $signers = [];
        foreach ($this->casefile->getSigners() as $signer) {
            $request = $signer->getSigningRequest();
            $signers[$signer->getId()] = [
                'id' => $signer->getId(),
                'name' => $signer->getName(),
                'email' => $request->getEmail(),
                'status' => $request->getStatus(),
                'rejectReason' => $request->getRejectReason(),
                'assets' => $request->getLink()
            ];
        }
        return $signers;
    }

    public function setFolder(
        Folder $folder
    ){
        $folder->addCaseFile($this->casefile);
        return $this;
    }

    public function setTemplate(
        CaseFileTemplate $casefileTemplate
    ){
        $this->casefile->setCaseFileTemplate($casefileTemplate);
        return $this;
    }

    public function addDocuments(
        Array $documents
    ){
        foreach($documents AS $document) {
            $this->addDocument(...$document);
        }
        return $this;
    }

    public function addDocument(
        String $title,
        String $filename,
        Int $documentTypeId = 0
    ){
        $documentTypes = $this->casefile->getDocumentTypes();

        $document = new Document($this->casefile);
        $document->setTitle($title);
        $document->setPdfFile($filename);
        $document->makeSignable();
        if (isset($documentTypes[$documentTypeId])) {
            $document->setDocumentType($documentTypes[$documentTypeId]);
        }
        Document::persist($document);
    }

    public function getDocuments() {
        $documents = [];
        foreach ($this->casefile->getDocuments() as $document) {
            $documents[$document->getId()] = [
                'id' => $document->getId(),
                'status' => $document->getStatus(),
                'title' => $document->getTitle(),
                'created' => $document->getCreatedAt(),
            ];
        }
        return $documents;
    }

    public function status()
    {
        return $this->getElement()->getStatus();
    }

    public function title()
    {
        return $this->getElement()->getTitle();
    }

    public function getElement()
    {
        if ($this->casefile) {
            return $this->casefile;
        } elseif ($this->document) {
            return $this->document;
        }
    }

    public function getTemplates()
    {
        $casefileTemplates = $this->casefile->getCaseFileTemplates();

        foreach($casefileTemplates AS $casefileTemplate) {
            $templates[$casefileTemplate->getId()] = $casefileTemplate->getName();
        }

        return $templates;
    }

    public function getTemplate($arg) {

        $templates = $this->casefile->getCaseFileTemplates();

        if (is_int($arg)) {
            $template = array_filter($templates, function ($obj) use ($arg) {
                return $obj->getId() == $arg;
            });
        } elseif (is_string($arg)) {
            $template = array_filter($templates, function ($obj) use ($arg) {
                return $obj->getName() == $arg;
            });
        }

        if ($template) {
            return current($template);
        }
    }

    public function getFolders()
    {
        return Folder::findAll();
    }

    public function getFolder($arg) {
        if (is_int($arg)) {
            return Folder::find($arg);
        } elseif (is_string($arg)) {
            $folder = Folder::findOneBy(['title' => $arg]);
            if ($folder) {
                return current($folder);    
            }
        }
    }

    public function showTemplate() {
        $casefileTemplate = $this->casefile->getCaseFileTemplate();
        return $this->parseTemplate($casefileTemplate);
    }

    public function parse()
    {
        if (!$this->getElement()->getId()) {
            return false;
        }

        $this->response = [
            'id' => $this->getElement()->getId(),
            'status' => $this->getElement()->getStatus(),
            'created' => $this->getElement()->getCreatedAt(),
            'title' => $this->getElement()->getTitle()
        ];

        if ($this->casefile) {
            $this->response['documents'] = $this->getDocuments();
            $this->response['signers'] = $this->getSigners();
        }

        return $this;
    }

    public function parseTemplate(
        Object $casefileTemplate
    ){
        $documentTypes = [];
            
        foreach($casefileTemplate->getDocumentTypes() AS $documentTypeKey => $documentType) {

            $documentTypes[$documentTypeKey] = [
                'id' => $documentType->getId(),
                'name' => $documentType->getName(),
            ];

            $signerTypes = [];

            foreach($documentType->getSignerTypes() AS $signerTypeKey => $signerType) {
                $signerTypes[] = ['id' => $signerTypeKey, 'name' => $signerType->getName()];
            }

            if (count($signerTypes)) {
                $documentTypes[$documentTypeKey]['signerTypes'] = $signerTypes;
            }

        }

        return [
            'id' => $casefileTemplate->getId(),
            'name' => $casefileTemplate->getName(),
            'documentTypes' => $documentTypes
        ];
    }

    public function __toString()
    {
        $this->parse();
        return json_encode($this->response, JSON_PRETTY_PRINT).PHP_EOL;
    }
}
