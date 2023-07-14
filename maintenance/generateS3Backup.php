<?php

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

use Aws\S3\S3Client;

class GenerateS3Backup extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'filename', 'Filename of the .tar.gz dump', true, true );
	}

	public function execute() {
		$limits = [ 'memory' => 0, 'filesize' => 0, 'time' => 0, 'walltime' => 0 ];

		$wiki = $this->getConfig()->get( 'DBname' );
		$this->output( "Starting Amazon S3 dump backup for $wiki...\n" );

		// Available disk space must be 5GB
		$df = disk_free_space( '/tmp' );
		if ($df < 5 * 1024 * 1024 * 1024) {
			$this->error( "Not enough disk space available (< 5GB). Aborting dump.\n" );
			return;
		}

		// If no wiki then error
		if ( !$wiki ) {
			$this->error( 'No wiki has been defined' );
			return;
		}

		// Amazon S3 configuration
		$bucketName = $this->getConfig()->get( 'AWSBucketName' );
		$region = $this->getConfig()->get( 'AWSRegion' );
		$key = $this->getConfig()->get( 'AWSCredentials' )['key'];
		$secret = $this->getConfig()->get( 'AWSCredentials' )['secret'];

		$client = new S3Client( [
			'region' => $region,
			'version' => 'latest',
			'credentials' => [
				'key' => $key,
				'secret' => $secret,
			],
		] );

		$objects = $client->getIterator( 'ListObjects', [
			'Bucket' => $bucketName,
			'Prefix' => $wiki,
		] );

		$usage = 0;
		foreach ( $objects as $object ) {
			$client->getObject( [
				'Bucket' => $bucketName,
				'Key' => $object['Key'],
				'SaveAs' => "/tmp/$wiki",
			] );
		}

		// Compress S3 object (.tar.gz)
		shell_exec( "tar -czf /tmp/{$this->getOption('filename')} /tmp/$wiki --remove-files" );

		$this->output( "Amazon S3 dump backup for $wiki complete!\n" );
	}
}

$maintClass = GenerateS3Backup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
