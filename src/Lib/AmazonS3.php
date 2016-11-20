<?php
namespace CakephpAmazonS3\Lib;

use Cake\Core\Configure;
use Cake\Core\Exception\Exception;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * AmazonS3
 * 
 */
class AmazonS3
{

    /** 
     * api version to use
     * @var string
     */
    private $version = '2006-03-01';
    
    /**
     * S3 key
     * 
     * @var string
     */
    private $key = null;

    /**
     * S3 secret
     * 
     * @var string
     */
    private $secret = null;

    /**
     * S3 Bucket name
     * 
     * @var string
     */
    private $bucket = null;

    /**
     * bucket's region
     * 
     * @var string
     */
    private $region = null;

    /**
     *
     * @var string
     */
    private $endpoint = null;

    /**
     * @var \Aws\S3\S3Client
     */
    private $s3Client = null;
    
    
    /**
     * object permission in s3
     * private|public-read|public-read-write|authenticated-read|aws-exec-read|bucket-owner-read|bucket-owner-full-control 
     * @link http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#putobject
     */
    const ACL_PRIVATE = 'private';
    const ACL_PUBLIC_READ = 'public-read';
    const ACL_PUBLIC_READ_WRITE = 'public-read-write';
    const ACL_AUTHENTICATED_READ = 'authenticated-read';
    const ACL_AWS_EXEC_READ = 'aws-exec-read';
    const ACL_BUCKET_OWNER_READ = 'bucket-owner-read';
    const ACL_BUCKET_OWNER_FULL_CONTROL = 'bucket-owner-full-control';
    
    /** 
     * default permission for objects created in s3
     * @var string
     */
    private $permission = self::ACL_PRIVATE;

    
    /**
     *
     * {@inheritDoc}
     *
     * @see \Cake\Controller\Component::initialize()
     */
    public function __construct(array $config = [])
    {
        $configData = Configure::read('CakephpAmazonS3');
        $settings = [];
        if (! empty($configData)) {
            $settings = $configData;
        }
        
        foreach ($settings as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        if (empty($this->key) || empty($this->bucket) || empty($this->region)) {
            throw new Exception('Key, bucket or region are missing');
        }

        $this->s3Client = new S3Client([
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret
            ],
            'endpoint' => $this->endpoint,
            'region' => $this->region,
            'version' => $this->version
        ]);
    }

    /**
     * @param string $localFile - full path to file to be uploaded
     * @param string $remoteFile - file "path" in s3 - relative the bucket name (without starting slash)
     * @param string $permission
     * @param array $headers - all the rest options by
     *         @link http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#putobject
     * @return boolean
     */
    public function putObject($localFile, $remoteFile, $permission = null, $headers = [])
    {
        $localFile = trim($localFile);
        if ($localFile != '' && !file_exists($localFile)) {
            return false;
        }
        
        $args = [
            'Bucket' => $this->bucket,
            'Key' => $remoteFile,
            'Body' => $localFile == '' ? '' : fopen($localFile, 'r'),
            'ACL' => $this->getPermission($permission)
        ];

        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $args[$key] = $value;
            }
        }

        try {
            $result = $this->s3Client->putObject($args);
            $metadata = $result->get('@metadata');
            
            if (!empty($metadata['statusCode']) && $metadata['statusCode'] == 200) {
                return true;
            }
        } catch (S3Exception $e) {
            
        }
        
        return false;
    }
    
    
    /**
     * listObjects method
     * returns the list of object in the given path
     * 
     * @param string $path
     * @return \Aws\Result|boolean
     */
    public function listObjects($path)
    {
        try {
            $result = $this->s3Client->listObjects([
                'Bucket' => $this->bucket,
                'Marker' => $path
            ]);
            
            $metadata = $result->get('@metadata');
            
            if (!empty($metadata['statusCode']) && $metadata['statusCode'] == 200) {
                $items = $result->get('Contents');
                return $items;
            }
        } catch (S3Exception $e) {
            
        }
        
        return false;
    }

    
    public function getObjectUrl($path, $time = '+2 minutes')
    {
        $cmd = $this->s3Client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $path
        ]);
        
        $request = $this->s3Client->createPresignedRequest($cmd, $time);
        
        try {
            // Get the actual presigned-url
            $presignedUrl = (string) $request->getUri();        
            
            if (is_string($presignedUrl)) {
                return $presignedUrl;
            }
        } catch (Exception $e) {
        }

        return false;
    }
    
    
    /**
     * Creates folder (AKA prefix) in S3
     * 
     * @param string $path
     * @param string $permission
     * @return boolean
     */
    public function createFolder($path, $permission = null)
    {
        $path = trim($path, '/').'/';
        
        try {
            return $this->putObject('', $path, $permission);
        } catch (S3Exception $e) {
            
        }
    
        return false;
    }
    
    
    /**
     * validates and returns the permission
     * 
     * @param string $permission
     */
    private function getPermission($permission = null)
    {
        if ($permission === null) {
            return $this->permission;
        }

        $refl = new \ReflectionClass($this);
        $constants = $refl->getConstants();
        
        if (in_array($permission, $constants)) {
            return $permission;
        }
        
        return $this->permission;
    }
    
    
    
    
}
