<?php
namespace CakephpAmazonS3\Lib;

use Cake\Core\Configure;
use Cake\Core\Exception\Exception;
use Aws\S3\S3Client;

/**
 * AmazonS3
 * 
 */
class AmazonS3
{

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
            'version' => 'latest'
        ]);
    }

    /**
     * @param string $localFile
     * @param string $remoteFile
     * @param string $permission
     * @param array $options - all the rest options by
     *         @link http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.S3.S3Client.html#_putObject
     * @return boolean
     */
    public function putObject($localFile, $remoteFile, $permission = null, $options = [])
    {
        if (!file_exists($localFile)) {
            return false;
        }
        
        $args = [
            'Bucket' => $this->bucket,
            'Key' => $remoteFile,
            'Body' => fopen($localFile, 'r'),
            'ACL' => 'public-read'
        ];

        if (!empty($options)) {
            foreach ($options as $key => $value) {
                $args[$key] = $value;
            }
        }

        $result = $this->s3Client->putObject($args);
        $metadata = $result->get('@metadata');
        
        if (!empty($metadata['statusCode']) && $metadata['statusCode'] == 200) {
            return true;
        }
        
        return false;
    }
    
    
    
    
    
    
}
