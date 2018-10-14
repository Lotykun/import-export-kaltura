<?php
namespace UEFORMA\ImportExportKaltura;

use UEFORMA\ImportExportKaltura\Helpers;
use UEFORMA\ImportExportKaltura\LoggerImplementation;
use Kaltura\Client\Configuration as KalturaConfiguration;
use Kaltura\Client\Client as KalturaClient;
use Kaltura\Client\Enum\SessionType as KalturaSessionType;
use Kaltura\Client\ApiException;
use Kaltura\Client\ClientException;
use Kaltura\Client\Type\MediaEntryFilter as KalturaMediaEntryFilter;
use Kaltura\Client\Type\FlavorAssetFilter as KalturaFlavorAssetFilter;
use Kaltura\Client\Type\MediaEntry as KalturaMediaEntry;
use Kaltura\Client\Type\CategoryFilter as KalturaCategoryFilter;
use Kaltura\Client\Type\Category as KalturaCategory;
use Kaltura\Client\Type\UploadToken as KalturaUploadToken;
use Kaltura\Client\Type\UploadedFileTokenResource as KalturaUploadedFileTokenResource;
//use Kaltura\Client\Type\CategoryEntry as KalturaCategoryEntry;
use Kaltura\Client\Type\FilterPager as KalturaMediaEntryPager;
use Kaltura\Client\Enum\MediaEntryOrderBy as KalturaMediaEntryOrderBy;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;

class KalturaModel {
    public $_session = null;
    public $_location = null;
    public $_client = null;
    public $_userId = null;
    public $_partnerId = null;

    public function __construct($sufix) {
        $this->_location    = $sufix;
        $this->_userId    = Helpers::getOption('userId'.$sufix);
        $this->_partnerId = Helpers::getOption('partnerId'.$sufix);
        
        $config = new KalturaConfiguration();
        $config->setServiceUrl(Helpers::getOption('serviceUrl'.$sufix));
        $config->setCurlTimeout(120);
        $config->setLogger(new LoggerImplementation());
        
        // init kaltura client
        $client = new KalturaClient($config);

        // generate session
        $ks = $client->generateSession(
                Helpers::getOption('adminSecret'.$sufix),
                Helpers::getOption('userId'.$sufix), 
                KalturaSessionType::ADMIN,
                Helpers::getOption('partnerId'.$sufix));
        $config->getLogger()->log('Kaltura session (ks) was generated successfully: ' . $ks);
        $client->setKs($ks);

        // check connectivity
        try{
            $client->getSystemService()->ping();
        } catch (ApiException $ex) {
            $config->getLogger()->log('Ping failed with api error: '.$ex->getMessage());
            die;
        } catch (ClientException $ex) {
            $config->getLogger()->log('Ping failed with client error: '.$ex->getMessage());
            die;
        }
        
        $this->_session = $ks;
        $this->_client = $client;
    }
    
    public function download_temp_mp4($entry){
        /*$connectionString = "DefaultEndpointsProtocol=https;AccountName="
                .Helpers::getOption('azure_storage_account_name').";AccountKey="
                .Helpers::getOption('azure_storage_account_primary_access_key');
        $container = "code";
        // Create blob client.
        $blobClient = BlobRestProxy::createBlobService($connectionString);
        
        $filename = date("YmdHis",time())."_".$entry->id.'.mp4';
        $ctx = stream_context_create();
        stream_context_set_params($ctx, array("notification" => array(&$this, "stream_notification_callback")));
        $content = fopen($entry->downloadUrl, "r", false, $ctx);
        //Upload blob
        $blobClient->createBlockBlob($container, "kaltura/tmp/".$filename, $content);
        //fclose($content);
        //unlink($fileToUpload['path']);*/
        
        
        
        $destination_path = UEFORMA_IMPORTEXPORTKALTURA_ROOT.'/tempvideo/';
        $ctx = stream_context_create();
        stream_context_set_params($ctx, array("notification" => array(&$this, "stream_notification_callback")));
        $video_temp_content = fopen($entry->downloadUrl, "r", false, $ctx);
        $video_temp_name = $destination_path.$entry->id.'.mp4';
        $result = (is_resource($video_temp_content) && file_put_contents($video_temp_name, $video_temp_content)) ? TRUE : FALSE;
        
        return $result;
    }
    
    public function upload_temp_mp4($entry, $categoryOrigin){
        $origin_path = UEFORMA_IMPORTEXPORTKALTURA_ROOT.'/tempvideo/';
        $client = $this->_client;
        try{
            $categoryDest = $this->get_category($categoryOrigin);
            $kalturaEntry = new KalturaMediaEntry();
            
            $kalturaEntry->name = $entry->name;
            $kalturaEntry->mediaType = 1;
            $kalturaEntry->categoriesIds = $categoryDest->id;
            $kalturaEntry->tags = $entry->tags;
            $kalturaEntry->creatorId = $entry->creatorId;
            $kalturaEntry->userId = $entry->userId;
            $newEntry = $client->media->add($kalturaEntry);
            
            // Subimos el archivo
            $uploadToken = new KalturaUploadToken();
            $uploadToken = $client->uploadToken->add($uploadToken);

            $fileData = $origin_path.$entry->id.'.mp4';
            $resume = null;
            $finalChunk = null;
            $resumeAt = null;
            //$fileData = "https://diariomedico.blob.core.windows.net/code/kaltura/0_hfyau2cr.mp4";
            $tokenUploaded = $client->uploadToken->upload($uploadToken->id, $fileData, $resume, $finalChunk, $resumeAt);

            $resource = new KalturaUploadedFileTokenResource();
            $resource->token = $tokenUploaded->id;
            $result = $client->media->addContent($newEntry->id, $resource);
            
            $return = $this->addThumbanilFromUrl($newEntry->id, $entry->thumbnailUrl);
            $thumbAssetId = $return->id;
            $this->setThumbanilAsDefault($thumbAssetId);
            
            unlink($fileData);
        }catch(Exception $e){
            $result = FALSE;
        }
        return $result;
    }
    
    public function list_entries($filter = null, $pager = null){
        return $this->_client->media->listAction($filter, $pager);
    }
    
    public function list_media_entries(){
        $filter = new KalturaMediaEntryFilter();
        $filter->orderBy = KalturaMediaEntryOrderBy::CREATED_AT_DESC;
        return $this->list_entries($filter);
    }
    
    public function list_media_entries_paged($page, $per_page){
        $filter = new KalturaMediaEntryFilter();
        $filter->statusIn = "1,2,4";
        $filter->orderBy = KalturaMediaEntryOrderBy::CREATED_AT_DESC;
        $pager = new KalturaMediaEntryPager();
        $pager->pageIndex = $page;
        $pager->pageSize = $per_page;
        return $this->list_entries($filter, $pager);
    }
    
    public function getFlavorsByEntryId( $entryId ) {
        return $this->_client->flavorAsset->getByEntryId($entryId);
    }
        
    public function listFlavorsByEntryId($entryId) {
        $filter = new KalturaFlavorAssetFilter();
        $filter->entryIdEqual = $entryId;
        $filter->entryIdIn = $entryId;

        return $this->_client->flavorAsset->listAction($filter);
    }
    
    public function stream_notification_callback($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max) {
        static $filesize = null;

        switch($notification_code) {
        case STREAM_NOTIFY_RESOLVE:
        case STREAM_NOTIFY_AUTH_REQUIRED:
        case STREAM_NOTIFY_COMPLETED:
        case STREAM_NOTIFY_FAILURE:
        case STREAM_NOTIFY_AUTH_RESULT:
            /* Ignore */
            break;

        case STREAM_NOTIFY_REDIRECTED:
            echo "Redireccionando a: ", $message, "\n";
            break;

        case STREAM_NOTIFY_CONNECT:
            echo "Conectado...\n";
            break;

        case STREAM_NOTIFY_FILE_SIZE_IS:
            $filesize = $bytes_max;
            echo "Tamaño de archivo: ", $filesize, "\n";
            break;

        case STREAM_NOTIFY_MIME_TYPE_IS:
            echo "Tipo mime: ", $message, "\n";
            break;

        case STREAM_NOTIFY_PROGRESS:
            if ($bytes_transferred > 0) {
                if (!isset($filesize)) {
                    echo printf("\rTamaño de archivo desconocido.. %2d kb hecho..", $bytes_transferred/1024);
                } else {
                    $length = (int)(($bytes_transferred/$filesize)*100);
                    echo printf("\r[%-100s] %d%% (%2d/%2d kb)", str_repeat("=", $length). ">", $length, ($bytes_transferred/1024), $filesize/1024);
                }
            }
            break;
        }
    }
    
    public function get_category($category){
        $filter = new KalturaCategoryFilter();
        $filter->fullNameEqual = $category->fullName;
        $searchResponse = $this->_client->category->listAction($filter);
        if ($searchResponse->totalCount>0) {
            $categoryDest = $searchResponse->objects[0];
        } else {
            $newCategory = new KalturaCategory();
            $newCategory->name = $category->name;
            $newCategory->description = $category->description;
            $categoryDest = $this->_client->category->add($newCategory);
        }
        return $categoryDest;
    }
    
    public function addThumbanilFromUrl($entryId, $url) {
        return $this->_client->thumbAsset->addFromUrl($entryId, $url);
    }
    
    public function setThumbanilAsDefault($thumbAssetId) {
        return $this->_client->thumbAsset->setAsDefault($thumbAssetId);
    }
    
    public function is_entry_ready($entry) {
        $response = array();
        $flavors = $this->getFlavorsByEntryId($entry->id);
        $response['flavorstotal'] = count($flavors);
        $flavorsOK = 0;
        foreach ($flavors as $flavor) {
            if ($flavor->status == 2) {
                $flavorsOK++;
            }
        }
        $response['flavorsok'] = $flavorsOK;
        return $response;
    }
}