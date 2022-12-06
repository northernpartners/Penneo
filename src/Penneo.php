<?php

namespace NPDigital\Api;

use Penneo\SDK\ApiConnector;
use Penneo\SDK\CaseFile;
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

    static function casefile(Int $casefileId = 0){

        $api = new Penneo();

        if ($casefileId > 0) {
            try {
                $api->casefile = CaseFile::find($casefileId);
            } catch (Exception $e) {
                $api->casefile = false;
            }
        } else {
            $api->casefile = new CaseFile();
        }

        return $api;
    }

    static function document($documentId) 
    {
        $api = new Penneo();
        try {
            $api->document = Document::find($documentId);
        } catch (Exception $e) {
            $api->document = false;
        }

        return $api;
    }

    public function create(
        String $title = '',
        Array $documents,
        Array $signers,
        String $language = 'en',
        Int $templateId = 0
    ){
        if ($this->casefile) {
            // Casefile meta data
            $this->casefile->setTitle($title);
            $this->casefile->setLanguage($language);

            // Set template
            $templates = $this->casefile->getCaseFileTemplates();
            if (isset($templates[$templateId])) {
                $this->casefile->setCaseFileTemplate($templates[$templateId]);
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

    public function send(){
        if ($this->casefile) {
            $this->casefile->send();
        }
        return $this;
    }

    public function addSigners(Array $signers){
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

    public function addDocuments(Array $documents){
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

    public function parse()
    {
        $this->response = [
            'id' => $this->getElement()->getId(),
            'status' => $this->getElement()->getStatus(),
            'created' => $this->getElement()->getCreatedAt(),
            'title' => $this->getElement()->getTitle()
        ];

        if ($this->casefile) {
            foreach ($this->casefile->getDocuments() as $document) {
                $this->response['documents'][] = [
                    'id' => $document->getId(),
                    'status' => $document->getStatus(),
                    'title' => $document->getTitle(),
                    'created' => $document->getCreatedAt(),
                ];
            }
        }

        return $this;
    }

    public function __toString()
    {
        $this->parse();
        return json_encode($this->response, JSON_PRETTY_PRINT).PHP_EOL;
    }
}
