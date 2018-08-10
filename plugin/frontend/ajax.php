<?php
namespace Sgdg\Frontend\Ajax;

function register() {
	add_action( 'wp_ajax_list_dir', '\\Sgdg\\Frontend\\Ajax\\handle_ajax' );
	add_action( 'wp_ajax_nopriv_list_dir', '\\Sgdg\\Frontend\\Ajax\\handle_ajax' );
}

function handle_ajax() {
	try {
		$client = \Sgdg\Frontend\GoogleAPILib\get_drive_client();
	} catch ( \Exception $e ) {
		wp_send_json( [ 'error' => esc_html__( 'Not authorized.', 'skaut-google-drive-gallery' ) ] );
	}
	$root_path = \Sgdg\Options::$root_path->get();
	$dir       = end( $root_path );

	// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
	$config_path = get_transient( 'sgdg_nonce_' . $_GET['nonce'] );

	if ( false === $config_path ) {
		wp_send_json( [ 'error' => esc_html__( 'The gallery has expired.', 'skaut-google-drive-gallery' ) ] );
	}

	if ( '' !== $config_path ) {
		$path = explode( '/', trim( $config_path, " /\t\n\r\0\x0B" ) );
		$dir  = find_dir( $client, $dir, $path );
	}

	if ( ! $dir ) {
		wp_send_json( [ 'error' => esc_html__( 'No such gallery found.', 'skaut-google-drive-gallery' ) ] );
	}
	$ret = [];
	// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
	if ( isset( $_GET['path'] ) && '' !== $_GET['path'] ) {

		// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
		$path        = explode( '/', $_GET['path'] );
		$ret['path'] = path_names( $client, $path );
		$dir         = apply_path( $client, $dir, $path );
	}
	$directories = directories( $client, $dir );
	if ( is_string( $directories ) ) {
		wp_send_json( [ 'error' => $directories ] );
	}
	$ret['directories'] = $directories;
	$ret['images']      = images( $client, $dir );
	$ret['videos']      = videos( $client, $dir ); // TODO: Gate this by an option
	wp_send_json( $ret );
}

function find_dir( $client, $root, array $path ) {
	$page_token = null;
	do {
		$params   = [
			'q'                     => '"' . $root . '" in parents and mimeType = "application/vnd.google-apps.folder" and trashed = false',
			'supportsTeamDrives'    => true,
			'includeTeamDriveItems' => true,
			'pageToken'             => $page_token,
			'pageSize'              => 1000,
			'fields'                => 'nextPageToken, files(id, name)',
		];
		$response = $client->files->listFiles( $params );
		foreach ( $response->getFiles() as $file ) {
			if ( $file->getName() === $path[0] ) {
				if ( count( $path ) === 1 ) {
					return $file->getId();
				}
				array_shift( $path );
				return find_dir( $client, $file->getId(), $path );
			}
		}
		$page_token = $response->getNextPageToken();
	} while ( null !== $page_token );
	return null;
}

function apply_path( $client, $root, array $path ) {
	$page_token = null;
	do {
		$params   = [
			'q'                     => '"' . $root . '" in parents and mimeType = "application/vnd.google-apps.folder" and trashed = false',
			'supportsTeamDrives'    => true,
			'includeTeamDriveItems' => true,
			'pageToken'             => $page_token,
			'pageSize'              => 1000,
			'fields'                => 'nextPageToken, files(id)',
		];
		$response = $client->files->listFiles( $params );
		foreach ( $response->getFiles() as $file ) {
			if ( $file->getId() === $path[0] ) {
				if ( count( $path ) === 1 ) {
					return $file->getId();
				}
				array_shift( $path );
				return apply_path( $client, $file->getId(), $path );
			}
		}
		$page_token = $response->getNextPageToken();
	} while ( null !== $page_token );
	return null;
}

function path_names( $client, array $path, array $used_path = [] ) {
	$ret = [];
	foreach ( $path as $segment ) {
		$response = $client->files->get( $segment, [
			'supportsTeamDrives' => true,
			'fields'             => 'name',
		]);
		$ret[]    = [
			'id'   => $segment,
			'name' => $response->getName(),
		];
	}
	return $ret;
}

function directories( $client, $dir ) {
	$dir_counts_allowed = \Sgdg\Options::$dir_counts->get() === 'true';
	$ids                = [];
	$names              = [];

	$page_token = null;
	do {
		$params   = [
			'q'                     => '"' . $dir . '" in parents and mimeType = "application/vnd.google-apps.folder" and trashed = false',
			'supportsTeamDrives'    => true,
			'includeTeamDriveItems' => true,
			'orderBy'               => \Sgdg\Options::$dir_ordering->get(),
			'pageToken'             => $page_token,
			'pageSize'              => 1000,
			'fields'                => 'nextPageToken, files(id, name)',
		];
		$response = $client->files->listFiles( $params );
		foreach ( $response->getFiles() as $file ) {
			$ids[]   = $file->getId();
			$names[] = $file->getName();
		}
		$page_token = $response->getNextPageToken();
	} while ( null !== $page_token );

	$client->getClient()->setUseBatch( true );
	$batch = $client->createBatch();
	dir_images_requests( $client, $batch, $ids );
	if ( $dir_counts_allowed ) {
		dir_counts_requests( $client, $batch, $ids );
	}
	$responses = $batch->execute();
	$client->getClient()->setUseBatch( false );

	try {
		$dir_images = dir_images_responses( $responses, $ids );
		if ( $dir_counts_allowed ) {
			$dir_counts = dir_counts_responses( $responses, $ids );
		}
	} catch ( \Sgdg\Vendor\Google_Service_Exception $e ) {
		if ( 'userRateLimitExceeded' === $e->getErrors()[0]['reason'] ) {
			return esc_html__( 'The maximum number of requests has been exceeded. Please try again in a minute.', 'skaut-google-drive-gallery' );
		} else {
			return $e->getErrors()[0]['message'];
		}
	}

	$ret   = [];
	$count = count( $ids );
	for ( $i = 0; $i < $count; $i++ ) {
		$val = [
			'id'        => $ids[ $i ],
			'name'      => $names[ $i ],
			'thumbnail' => $dir_images[ $i ],
		];
		if ( $dir_counts_allowed ) {
			$val = array_merge( $val, $dir_counts[ $i ] );
		}
		$ret[] = $val;
	}
	return $ret;
}

function dir_images_requests( $client, $batch, $dirs ) {
	$params = [
		'supportsTeamDrives'    => true,
		'includeTeamDriveItems' => true,
		'orderBy'               => \Sgdg\Options::$image_ordering->get(),
		'pageSize'              => 1,
		'fields'                => 'files(imageMediaMetadata(width, height), thumbnailLink)',
	];

	foreach ( $dirs as $dir ) {
		$params['q'] = '"' . $dir . '" in parents and mimeType contains "image/" and trashed = false';
		$request     = $client->files->listFiles( $params );
		$batch->add( $request, 'img-' . $dir );
	}
}

function dir_counts_requests( $client, $batch, $dirs ) {
	$params = [
		'supportsTeamDrives'    => true,
		'includeTeamDriveItems' => true,
		'pageSize'              => 1000,
		'fields'                => 'files(id)',
	];

	foreach ( $dirs as $dir ) {
		$params['q'] = '"' . $dir . '" in parents and mimeType contains "application/vnd.google-apps.folder" and trashed = false';
		$request     = $client->files->listFiles( $params );
		$batch->add( $request, 'dircount-' . $dir );
		$params['q'] = '"' . $dir . '" in parents and mimeType contains "image/" and trashed = false';
		$request     = $client->files->listFiles( $params );
		$batch->add( $request, 'imgcount-' . $dir );
		$params['q'] = '"' . $dir . '" in parents and mimeType contains "video/" and trashed = false';
		$request     = $client->files->listFiles( $params );
		$batch->add( $request, 'vidcount-' . $dir );
	}
}

function dir_images_responses( $responses, $dirs ) {
	$ret = [];
	foreach ( $dirs as $dir ) {
		$response = $responses[ 'response-img-' . $dir ];
		if ( $response instanceof \Sgdg\Vendor\Google_Service_Exception ) {
			throw $response;
		}
		$images = $response->getFiles();
		if ( count( $images ) === 0 ) {
			$ret[] = false;
		} else {
			$ret[] = substr( $images[0]->getThumbnailLink(), 0, -4 ) . ( $images[0]->getImageMediaMetadata()->getWidth() > $images[0]->getImageMediaMetadata()->getHeight() ? 'h' : 'w' ) . floor( 1.25 * \Sgdg\Options::$grid_height->get() );

		}
	}
	return $ret;
}

function dir_counts_responses( $responses, $dirs ) {
	$ret = [];
	foreach ( $dirs as $dir ) {
		$dir_response = $responses[ 'response-dircount-' . $dir ];
		$img_response = $responses[ 'response-imgcount-' . $dir ];
		$vid_response = $responses[ 'response-vidcount-' . $dir ];
		if ( $dir_response instanceof \Sgdg\Vendor\Google_Service_Exception ) {
			throw $dir_response;
		}
		if ( $img_response instanceof \Sgdg\Vendor\Google_Service_Exception ) {
			throw $img_response;
		}
		if ( $vid_response instanceof \Sgdg\Vendor\Google_Service_Exception ) {
			throw $vid_response;
		}
		$dircount   = count( $dir_response->getFiles() );
		$imagecount = count( $img_response->getFiles() );
		$vidcount   = count( $vid_response->getFiles() );

		$val = [];
		if ( $dircount > 0 ) {
			$val['dircount'] = $dircount . ' ' . esc_html( _n( 'folder', 'folders', $dircount, 'skaut-google-drive-gallery' ) );
		}
		if ( $imagecount > 0 ) {
			$val['imagecount'] = $imagecount . ' ' . esc_html( _n( 'image', 'images', $imagecount, 'skaut-google-drive-gallery' ) );
		}
		if ( $vidcount > 0 ) {
			$val['videocount'] = $vidcount . ' ' . esc_html( _n( 'video', 'videos', $vidcount, 'skaut-google-drive-gallery' ) );
		}
		$ret[] = $val;
	}
	return $ret;
}

function images( $client, $dir ) {
	$ret        = [];
	$page_token = null;
	do {
		$params   = [
			'q'                     => '"' . $dir . '" in parents and mimeType contains "image/" and trashed = false',
			'supportsTeamDrives'    => true,
			'includeTeamDriveItems' => true,
			'orderBy'               => \Sgdg\Options::$image_ordering->get(),
			'pageToken'             => $page_token,
			'pageSize'              => 1000,
			'fields'                => 'nextPageToken, files(id, thumbnailLink)',
		];
		$response = $client->files->listFiles( $params );
		foreach ( $response->getFiles() as $file ) {
			$ret[] = [
				'id'        => $file->getId(),
				'image'     => substr( $file->getThumbnailLink(), 0, -3 ) . \Sgdg\Options::$preview_size->get(),
				'thumbnail' => substr( $file->getThumbnailLink(), 0, -4 ) . 'h' . floor( 1.25 * \Sgdg\Options::$grid_height->get() ),
			];
		}
		$page_token = $response->getNextPageToken();
	} while ( null !== $page_token );
	return $ret;
}

function videos( $client, $dir ) {
	$ret        = [];
	$page_token = null;
	do {
		$params   = [
			'q'                     => '"' . $dir . '" in parents and mimeType contains "video/" and trashed = false',
			'supportsTeamDrives'    => true,
			'includeTeamDriveItems' => true,
			'orderBy'               => \Sgdg\Options::$image_ordering->get(), // TODO: Own option?
			'pageToken'             => $page_token,
			'pageSize'              => 1000,
			'fields'                => 'nextPageToken, files(id, mimeType, webContentLink, thumbnailLink)',
		];
		$response = $client->files->listFiles( $params );
		foreach ( $response->getFiles() as $file ) {
			$ret[] = [
				'id'        => $file->getId(),
				'src'       => $file->getWebContentLink(),
				'thumbnail' => substr( $file->getThumbnailLink(), 0, -4 ) . 'h' . floor( 1.25 * \Sgdg\Options::$grid_height->get() ),
				'mimeType'  => $file->getMimeType(),
			];
		}
		$page_token = $response->getNextPageToken();
	} while ( null !== $page_token );
	return $ret;
}
