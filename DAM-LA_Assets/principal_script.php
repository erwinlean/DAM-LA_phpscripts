<?php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Guzzle\Http\Client;

function go($assetsSearchPath)
{
	$serverUrl = assetsURL;

	// Log In
	$service = '/services/apilogin?';

	$client = new GuzzleHttp\Client();

	$params = [];
	$params['username'] = assetsUSERNAME;
	$params['password'] = assetsPASSWORD;

	$response = $client->request('POST', $serverUrl . $service . http_build_query($params));
	$jsonResponse = json_decode($response->getBody());
	$authToken = $jsonResponse->authToken;

	// Search uploaded assets
	$sservice = '/services/search?';

	$sclient = new GuzzleHttp\Client();

	$sparams = [];
	$sparams['q'] = 'ancestorPaths:"' . $assetsSearchPath . '"';
	$sparams['metadataToReturn'] = 'filename,extension,' . glaciarCODE;

	$headers = [];
	$headers['Authorization'] = 'Bearer ' . $authToken;

	$srequest = $sclient->request('POST', $serverUrl . $sservice . http_build_query($sparams) , ['headers' => $headers]);
	$sjsonResponse = json_decode($srequest->getBody());

	$totalHits = $sjsonResponse->totalHits;

	$now = date('Y/m/d H:i:s', time());
	$log  = $now . ' Going for ' . $totalHits . ' on ' . $assetsSearchPath . PHP_EOL;
	file_put_contents(logFILE, $log, FILE_APPEND);

	while($totalHits > 0)
	{
		set_time_limit(0);

		foreach($sjsonResponse->hits as $hit)
		{
			if($hit->metadata->assetDomain === 'image')
			{
				// RegEx to find EAN code
				if(preg_match('/\d{13,19}/', $hit->metadata->filename, $match))
				{
					// Leading zeros...
					$match[0] = ltrim($match[0], "0");
					if(isset($match[0]))
					{
						// Search assets that match EAN code
						$s2service = '/services/search?';

						$s2client = new GuzzleHttp\Client();

						$s2params = [];
						//$s2params['q'] = eanCODE . ':' . $match[0];
						$s2params['q'] = 'ancestorPaths:"'.assetsLA_PATH.'"' . eanCODE . ':' . $match[0];
						$s2params['metadataToReturn'] = 'filename, ' . glaciarCODE;

						$s2request = $s2client->request('POST', $serverUrl . $s2service . http_build_query($s2params) , ['headers' => $headers]);
						$s2jsonResponse = json_decode($s2request->getBody());

						if($s2jsonResponse->totalHits > 0)
						{
							// Download uploaded asset
							$dclient = new GuzzleHttp\Client();

							$resource = fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'wb');

							$response = $dclient->request('GET', $hit->originalUrl, ['headers' => $headers, 'sink' => $resource]);

							if(strpos($s2jsonResponse->hits[0]->metadata->filename, imageNamePATTERN) !== false)
							{
								// Update matched asset with the downloaded one
								$uservice = '/services/update?';

								$uclient = new GuzzleHttp\Client();

								$uparams = [];
								$uparams['id'] = $s2jsonResponse->hits[0]->id;
								$uparams['filename'] = $s2jsonResponse->hits[0]->metadata->{glaciarCODE};

								$multipart = [];
								$multipart['name'] = 'Filedata';
								$multipart['contents'] = fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'rb');

								$urequest = $uclient->request('POST', $serverUrl . $uservice  . http_build_query($uparams), [
																			'headers' => $headers,
																			'multipart' => [
																				[
																					'name' => 'Filedata',
																					'contents' => fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'rb')
																				]
																			]]);

								$ujsonResponse = json_decode($urequest->getBody());

								if($urequest->getStatusCode() == '200')
								{
									// List asset versions
									$vservice = '/services/version/list?';

									$vclient = new GuzzleHttp\Client();

									$vparams = [];
									$vparams['assetId'] = $s2jsonResponse->hits[0]->id;

									$vrequest = $vclient->request('POST', $serverUrl . $vservice . http_build_query($vparams) , ['headers' => $headers]);
									$vjsonResponse = json_decode($vrequest->getBody());

									for($i=1; $i<=$vjsonResponse->totalHits; $i++)
									{
										// Delete all versions
										$v2service = '/services/version/delete?';

										$v2client = new GuzzleHttp\Client();

										$v2params = [];
										$v2params['assetId'] = $vjsonResponse->hits[$i]->hit->metadata->id;
										$v2params['versions'] = $vjsonResponse->hits[$i]->hit->metadata->versionNumber;
										
										$v2request = $vclient->request('POST', $serverUrl . $v2service . http_build_query($v2params) , ['headers' => $headers]);
										$v2jsonResponse = json_decode($v2request->getBody());
									}

									// Remove
									$rservice = '/services/remove?';

									$rclient = new GuzzleHttp\Client();

									$rparams = [];
									$rparams['ids'] = $hit->id;

									$rrequest = $rclient->request('POST', $serverUrl . $rservice . http_build_query($rparams) , ['headers' => $headers]);
									$rjsonResponse = json_decode($rrequest->getBody());
								}
							}
							else
							{
								// Search masterId and check duplicates
								$s3service = '/services/search?';

								$s3client = new GuzzleHttp\Client();

								$s3params = [];
								$s3params['q'] = 'ancestorPaths:"'.assetsLA_PATH.'"' . eanCODE . ':' . $match[0];
								$s3params['metadataToReturn'] = 'masterId,folderPath,baseName,extension';

								$s3request = $s3client->request('POST', $serverUrl . $s3service . http_build_query($s3params) , ['headers' => $headers]);
								$s3jsonResponse = json_decode($s3request->getBody());

								foreach($s3jsonResponse->hits as $hit3)
								{
									$dclient = new GuzzleHttp\Client();
									$resource = fopen(assetsBATCH_PATH . '/' . $hit3->metadata->filename, 'wb');
									$response = $dclient->request('GET', $hit3->originalUrl, ['headers' => $headers, 'sink' => $resource]);

									$uploadedHash = hash_file('sha1', assetsBATCH_PATH . '/' . $hit->metadata->filename);
									$hit3Hash = hash_file('sha1', assetsBATCH_PATH . '/' . $hit3->metadata->filename);

									if($uploadedHash == $hit3Hash)
									{
										$duplicateId = $hit3;
									}

									if(!isset($hit3->metadata->masterId))
									{
										$masterId = $hit3;
									}
								}

								if(isset($duplicateId))
								{
									// Assuming Duplicate
									// Move
									$mservice = '/services/move?';

									$mclient = new GuzzleHttp\Client();

									$mparams = [];
									$mparams['source'] = $hit->metadata->assetPath;
									$mparams['target'] = assetsDUPLICATE_PATH . '/' . $hit->metadata->filename;

									$mrequest = $mclient->request('POST', $serverUrl . $mservice . http_build_query($mparams) , ['headers' => $headers]);
									$mjsonResponse = json_decode($mrequest->getBody());
								}
								else
								{
									// Copyng master asset
									$cservice = '/services/copy?';

									$cclient = new GuzzleHttp\Client();

									$cparams = [];
									$cparams['source'] = $masterId->metadata->assetPath;
									//$cparams['target'] = $masterId->metadata->assetPath;
									$cparams['target'] = $masterId->metadata->folderPath . '/' . $masterId->metadata->baseName . '.' . $hit->metadata->extension;

									$crequest = $cclient->request('POST', $serverUrl . $cservice . http_build_query($cparams) , ['headers' => $headers]);
									$cjsonResponse = json_decode($crequest->getBody());

									// Search assets with masterId
									$s4service = '/services/search?';

									$s4client = new GuzzleHttp\Client();

									$s4params = [];
									$s4params['q'] = 'ancestorPaths:"'.assetsLA_PATH.'"' . 'masterId:"' . $masterId->id . '"extension:' . $hit->metadata->extension;
									$s4params['metadataToReturn'] = 'filename, baseName';

									$s4request = $s4client->request('POST', $serverUrl . $s4service . http_build_query($s4params) , ['headers' => $headers]);
									$s4jsonResponse = json_decode($s4request->getBody());

									foreach($s4jsonResponse->hits as $hit4)
									{
										$autoRenameOffset = $s4jsonResponse->totalHits - 1;

										if($hit4->metadata->baseName == $masterId->metadata->baseName . '-' . $autoRenameOffset)
										{
											// Update
											$u2service = '/services/update?';

											$u2client = new GuzzleHttp\Client();

											$u2params = [];
											$u2params['id'] = $hit4->id;

											$multipart = [];
											$multipart['name'] = 'Filedata';
											$multipart['contents'] = fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'rb');

											$u2request = $u2client->request('POST', $serverUrl . $u2service  . http_build_query($u2params), [
																						'headers' => $headers,
																						'multipart' => [
																							[
																								'name' => 'Filedata',
																								'contents' => fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'rb')
																							]
																						]]);

											$u2jsonResponse = json_decode($u2request->getBody());

											if($u2request->getStatusCode() == '200')
											{
												// List versions
												$vservice = '/services/version/list?';

												$vclient = new GuzzleHttp\Client();

												$vparams = [];
												$vparams['assetId'] = $hit4->id;

												$vrequest = $vclient->request('POST', $serverUrl . $vservice . http_build_query($vparams) , ['headers' => $headers]);
												$vjsonResponse = json_decode($vrequest->getBody());

												for($i=1; $i<=$vjsonResponse->totalHits; $i++)
												{
													// Delete all versions
													$v2service = '/services/version/delete?';

													$v2client = new GuzzleHttp\Client();

													$v2params = [];
													$v2params['assetId'] = $vjsonResponse->hits[$i]->hit->metadata->id;
													$v2params['versions'] = $vjsonResponse->hits[$i]->hit->metadata->versionNumber;
													
													$v2request = $vclient->request('POST', $serverUrl . $v2service . http_build_query($v2params) , ['headers' => $headers]);
													$v2jsonResponse = json_decode($v2request->getBody());
												}

												// Remove
												$r2service = '/services/remove?';

												$r2client = new GuzzleHttp\Client();

												$r2params = [];
												$r2params['ids'] = $hit->id;

												$r2request = $r2client->request('POST', $serverUrl . $r2service . http_build_query($r2params) , ['headers' => $headers]);
												$r2jsonResponse = json_decode($r2request->getBody());
											}
										}
									}
								}
							}
						}
						else
						{
							// No results... Assuming No @noimage-
							// Move
							$mservice = '/services/move?';

							$mclient = new GuzzleHttp\Client();

							$mparams = [];
							$mparams['source'] = $hit->metadata->assetPath;
							$mparams['target'] = assetsNO_NOIMAGE_PATH . '/' . $hit->metadata->filename;

							$mrequest = $mclient->request('POST', $serverUrl . $mservice . http_build_query($mparams) , ['headers' => $headers]);
							$mjsonResponse = json_decode($mrequest->getBody());
						}
					}
				}
				else
				{
					// glaciarCODE
					if(preg_match_all('/\d{3,13}/', $hit->metadata->filename, $matches))
					{
						// var_dump($matches[0]);
						$foundMatch = false;

						foreach ($matches[0] as $match)
						{
							// Leading zeros...
							$match = ltrim($match, "0");
							if(isset($match))
							{
								// Search assets that match Glaciar code
								$s2service = '/services/search?';

								$s2client = new GuzzleHttp\Client();

								$s2params = [];
								$s2params['q'] = glaciarCODE . ':' . $match;
								$s2params['metadataToReturn'] = 'filename, ' . glaciarCODE;

								$s2request = $s2client->request('POST', $serverUrl . $s2service . http_build_query($s2params) , ['headers' => $headers]);
								$s2jsonResponse = json_decode($s2request->getBody());

								if($s2jsonResponse->totalHits > 0)
								{
									$foundMatch = true;
									// Download uploaded asset
									$dclient = new GuzzleHttp\Client();

									$resource = fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'wb');

									$response = $dclient->request('GET', $hit->originalUrl, ['headers' => $headers, 'sink' => $resource]);

									if(strpos($s2jsonResponse->hits[0]->metadata->filename, imageNamePATTERN) !== false)
									{
										// Update matched asset with the downloaded one
										$uservice = '/services/update?';

										$uclient = new GuzzleHttp\Client();

										$uparams = [];
										$uparams['id'] = $s2jsonResponse->hits[0]->id;
										$uparams['filename'] = $s2jsonResponse->hits[0]->metadata->{glaciarCODE};

										$multipart = [];
										$multipart['name'] = 'Filedata';
										$multipart['contents'] = fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'rb');

										$urequest = $uclient->request('POST', $serverUrl . $uservice  . http_build_query($uparams), [
																					'headers' => $headers,
																					'multipart' => [
																						[
																							'name' => 'Filedata',
																							'contents' => fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'rb')
																						]
																					]]);

										$ujsonResponse = json_decode($urequest->getBody());

										// var_dump($ujsonResponse);

										if($urequest->getStatusCode() == '200')
										{
											// List asset versions
											$vservice = '/services/version/list?';

											$vclient = new GuzzleHttp\Client();

											$vparams = [];
											$vparams['assetId'] = $s2jsonResponse->hits[0]->id;

											$vrequest = $vclient->request('POST', $serverUrl . $vservice . http_build_query($vparams) , ['headers' => $headers]);
											$vjsonResponse = json_decode($vrequest->getBody());

											for($i=1; $i<=$vjsonResponse->totalHits; $i++)
											{
												// Delete all versions
												$v2service = '/services/version/delete?';

												$v2client = new GuzzleHttp\Client();

												$v2params = [];
												$v2params['assetId'] = $vjsonResponse->hits[$i]->hit->metadata->id;
												$v2params['versions'] = $vjsonResponse->hits[$i]->hit->metadata->versionNumber;
												
												$v2request = $vclient->request('POST', $serverUrl . $v2service . http_build_query($v2params) , ['headers' => $headers]);
												$v2jsonResponse = json_decode($v2request->getBody());
											}

											// Remove
											$rservice = '/services/remove?';

											$rclient = new GuzzleHttp\Client();

											$rparams = [];
											$rparams['ids'] = $hit->id;

											$rrequest = $rclient->request('POST', $serverUrl . $rservice . http_build_query($rparams) , ['headers' => $headers]);
											$rjsonResponse = json_decode($rrequest->getBody());

										}
									}
									else
									{
										// Search masterId and check duplicates
										$s3service = '/services/search?';

										$s3client = new GuzzleHttp\Client();

										$s3params = [];
										$s3params['q'] = 'ancestorPaths:"'.assetsLA_PATH.'"' . glaciarCODE . ':' . $match;
										$s3params['metadataToReturn'] = 'masterId,folderPath,baseName,extension';

										$s3request = $s3client->request('POST', $serverUrl . $s3service . http_build_query($s3params) , ['headers' => $headers]);
										$s3jsonResponse = json_decode($s3request->getBody());

										// var_dump($s3jsonResponse);

										foreach($s3jsonResponse->hits as $hit3)
										{
											$dclient = new GuzzleHttp\Client();
											$resource = fopen(assetsBATCH_PATH . '/' . $hit3->metadata->filename, 'wb');
											$response = $dclient->request('GET', $hit3->originalUrl, ['headers' => $headers, 'sink' => $resource]);

											$uploadedHash = hash_file('sha1', assetsBATCH_PATH . '/' . $hit->metadata->filename);
											$hit3Hash = hash_file('sha1', assetsBATCH_PATH . '/' . $hit3->metadata->filename);

											if($uploadedHash == $hit3Hash)
											{
												$duplicateId = $hit3;
											}

											if(!isset($hit3->metadata->masterId))
											{
												$masterId = $hit3;
											}
										}

										if(isset($duplicateId))
										{
											// Assuming Duplicate
											// Move
											$mservice = '/services/move?';

											$mclient = new GuzzleHttp\Client();

											$mparams = [];
											$mparams['source'] = $hit->metadata->assetPath;
											$mparams['target'] = assetsDUPLICATE_PATH . '/' . $hit->metadata->filename;

											$mrequest = $mclient->request('POST', $serverUrl . $mservice . http_build_query($mparams) , ['headers' => $headers]);
											$mjsonResponse = json_decode($mrequest->getBody());
										}
										else
										{
											// Copyng master asset
											$cservice = '/services/copy?';

											$cclient = new GuzzleHttp\Client();

											$cparams = [];
											$cparams['source'] = $masterId->metadata->assetPath;
											//$cparams['target'] = $masterId->metadata->assetPath;
											$cparams['target'] = $masterId->metadata->folderPath . '/' . $masterId->metadata->baseName . '.' . $hit->metadata->extension;

											$crequest = $cclient->request('POST', $serverUrl . $cservice . http_build_query($cparams) , ['headers' => $headers]);
											$cjsonResponse = json_decode($crequest->getBody());

											// Search assets with masterId
											$s4service = '/services/search?';

											$s4client = new GuzzleHttp\Client();

											$s4params = [];
											$s4params['q'] = 'ancestorPaths:"'.assetsLA_PATH.'"' . 'masterId:"' . $masterId->id . '"extension:' . $hit->metadata->extension;
											$s4params['metadataToReturn'] = 'filename, baseName';

											$s4request = $s4client->request('POST', $serverUrl . $s4service . http_build_query($s4params) , ['headers' => $headers]);
											$s4jsonResponse = json_decode($s4request->getBody());

											foreach($s4jsonResponse->hits as $hit4)
											{
												$autoRenameOffset = $s4jsonResponse->totalHits - 1;

												if($hit4->metadata->baseName == $masterId->metadata->baseName . '-' . $autoRenameOffset)
												{
													// Update
													$u2service = '/services/update?';

													$u2client = new GuzzleHttp\Client();

													$u2params = [];
													$u2params['id'] = $hit4->id;

													$multipart = [];
													$multipart['name'] = 'Filedata';
													$multipart['contents'] = fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'rb');

													$u2request = $u2client->request('POST', $serverUrl . $u2service  . http_build_query($u2params), [
																								'headers' => $headers,
																								'multipart' => [
																									[
																										'name' => 'Filedata',
																										'contents' => fopen(assetsBATCH_PATH . '/' . $hit->metadata->filename, 'rb')
																									]
																								]]);

													$u2jsonResponse = json_decode($u2request->getBody());

													if($u2request->getStatusCode() == '200')
													{
														// List versions
														$vservice = '/services/version/list?';

														$vclient = new GuzzleHttp\Client();

														$vparams = [];
														$vparams['assetId'] = $hit4->id;

														$vrequest = $vclient->request('POST', $serverUrl . $vservice . http_build_query($vparams) , ['headers' => $headers]);
														$vjsonResponse = json_decode($vrequest->getBody());

														for($i=1; $i<=$vjsonResponse->totalHits; $i++)
														{
															// Delete all versions
															$v2service = '/services/version/delete?';

															$v2client = new GuzzleHttp\Client();

															$v2params = [];
															$v2params['assetId'] = $vjsonResponse->hits[$i]->hit->metadata->id;
															$v2params['versions'] = $vjsonResponse->hits[$i]->hit->metadata->versionNumber;
															
															$v2request = $vclient->request('POST', $serverUrl . $v2service . http_build_query($v2params) , ['headers' => $headers]);
															$v2jsonResponse = json_decode($v2request->getBody());
														}

														// Remove
														$r2service = '/services/remove?';

														$r2client = new GuzzleHttp\Client();

														$r2params = [];
														$r2params['ids'] = $hit->id;

														$r2request = $r2client->request('POST', $serverUrl . $r2service . http_build_query($r2params) , ['headers' => $headers]);
														$r2jsonResponse = json_decode($r2request->getBody());
													}
												}
											}
										}
									}
								}
							}
						}
						if(!$foundMatch)
						{
							// No results... Assuming No @noimage-
							// Move
							$mservice = '/services/move?';

							$mclient = new GuzzleHttp\Client();

							$mparams = [];
							$mparams['source'] = $hit->metadata->assetPath;
							$mparams['target'] = assetsNO_NOIMAGE_PATH . '/' . $hit->metadata->filename;

							$mrequest = $mclient->request('POST', $serverUrl . $mservice . http_build_query($mparams) , ['headers' => $headers]);
							$mjsonResponse = json_decode($mrequest->getBody());
						}
					}
					else
					{
						// No RegEx... Assuming No EAN & No Glaciar
						// Move
						$mservice = '/services/move?';

						$mclient = new GuzzleHttp\Client();

						$mparams = [];
						$mparams['source'] = $hit->metadata->assetPath;
						$mparams['target'] = assetsNO_EAN_PATH . '/' . $hit->metadata->filename;

						$mrequest = $mclient->request('POST', $serverUrl . $mservice . http_build_query($mparams) , ['headers' => $headers]);
						$mjsonResponse = json_decode($mrequest->getBody());
					}
				}
				if(isset($hit->metadata->filename))
				{
					if(file_exists(assetsBATCH_PATH . '/' . $hit->metadata->filename))
					{
						unlink(assetsBATCH_PATH . '/' . $hit->metadata->filename);
					}
				}
				if(isset($hit3))
				{
					foreach (glob(assetsBATCH_PATH . '/' . $hit3->metadata->filename . "*") as $possibleDelete)
					{
						if(file_exists($possibleDelete))
						{
							unlink($possibleDelete);
						}
					}
				}	
			}
			else
			{
				// Invalid formats
				// Move
				$mservice = '/services/move?';

				$mclient = new GuzzleHttp\Client();

				$mparams = [];
				$mparams['source'] = $hit->metadata->assetPath;
				$mparams['target'] = assetsINVALID_PATH . '/' . $hit->metadata->filename;

				$mrequest = $mclient->request('POST', $serverUrl . $mservice . http_build_query($mparams) , ['headers' => $headers]);
				$mjsonResponse = json_decode($mrequest->getBody());
			}
		}

		$srequest = $sclient->request('POST', $serverUrl . $sservice . http_build_query($sparams) , ['headers' => $headers]);
		$sjsonResponse = json_decode($srequest->getBody());

		$totalHits = $sjsonResponse->totalHits;

		if($totalHits > 0)
		{
			$now = date('Y/m/d H:i:s', time());
			$log  = $now . ' Still ' . $totalHits . ' to go...' . PHP_EOL;
			file_put_contents(logFILE, $log, FILE_APPEND);
		}
	}

	// Log Out
	$lservice = '/services/logout';

	$lclient = new GuzzleHttp\Client();

	$lrequest = $lclient->request('POST', $serverUrl . $lservice, ['headers' => $headers]);
	$ljsonResponse = json_decode($lrequest->getBody());
}

$now = date('Y/m/d H:i:s', time());
$log  = $now . ' Logging script process' . PHP_EOL;
file_put_contents(logFILE, $log, FILE_APPEND);

// Iterate Assets Search Paths
foreach(assetsSEARCH_PATHS as $assetsSearchPath)
{
	go($assetsSearchPath);
}

?>
