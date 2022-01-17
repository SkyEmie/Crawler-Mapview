<?php
	
	$numCrawl = 1000000;
	$urlStart = 'https://userdiag.com/cgu';

	ini_set('memory_limit', '1024M');
	ini_set('default_socket_timeout', 3);
	ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36');

	/* Créer json si il n'existe pas */
	if (!file_exists('db.json')) {
		file_put_contents('db.json', '');
	}

	/* Lire la base de données */
	$db = json_decode(file_get_contents('db.json'), True);


	/* Et c'est parti */
	for ($i=0; $i < $numCrawl; $i++) { 

		/* Crawler l'url et tout le tralala */
		$db = crawler($urlStart, $db);

		/* Prendre domaine suivant (pour s'écarter petit à petit) et aller le crawler */
		$listHost = [];
		foreach ($db as $ndd => $array) {
			/* Ne garder que les ndd non crawl */
			if ($db[$ndd]['crawl'] == False) {
				$listHost[] = $ndd;
			}
		}
		/* print_r($listHost); */
		if (isset($listHost[0])) {
			$urlStart = 'https://'.$listHost[0];
		} else {
			echo '[!] No more ndd to crawl before $numCrawl='.$numCrawl.'!'.PHP_EOL;
			exit;
		}

	}

	echo 'DONE!'.PHP_EOL;
	exit;




	/* -------------------------------------------------------------------------------- */



	function crawler($url, $db) {

		/* Récupérer contenu de la page */
		@$urlData = file_get_contents($url);

		/* Oui parfois c'est pas défini idk pourquoi */
		if (!isset($http_response_header)) {
			$urlHeader['reponse_code'] == 404;
		} else {
			$urlHeader = parseHeaders($http_response_header);
		}

		/* Oui parfois c'est pas défini idk pourquoi */
		if (!isset($urlHeader['reponse_code'])) {
			$urlHeader['reponse_code'] == 404;
		}

		$urlHost = parse_url($url, PHP_URL_HOST);
		echo 'MAIN URL: '.$url.PHP_EOL;
		/* print_r($urlHeader);*/

		/* Si main ok */
		if ($urlHeader['reponse_code'] == 200) {

			/* Créer l'entrée du ndd main */
			if (!isset($db[$urlHost])) {
				$db[$urlHost] = array(
					'crawl' => True,
					'inf' => array(),
					'loc' => array($url),
					'ext' => array(),
				);
				if (preg_match("/((?<!www|fr|en)\.(:?wordpress\.org|linkedin\.com|wikipedia\.org|facebook\.com))/i", $urlHost)) {
					$db[$urlHost]['crawl'] = True;
				}
			}

			/* Récupérer les liens dans la page */
			preg_match_all("/(?:href)=['\"](?<url>.*?)['\"]/i", $urlData, $urlMatches);
			foreach ($urlMatches[1] as $key => $urlSub) {

				$urlTrySub = True;
				$countMatches = count($urlMatches[1]);
	
				/* Si lien local, ajouter le ndd pour get les pages */
				if (substr($urlSub, 0, 1) == '/' && substr($urlSub, 0, 2) != '//' || str_contains($urlSub, 'https://'.$urlHost)) {

					/* Si url contient déjà https alors pas rajouter */
					if (substr($urlSub, 0, 4) != 'http') {
						$urlSub = 'https://'.$urlHost.$urlSub;
					}
					/* Enregistrer comme url locale */
					echo 'GET: ('.($key + 1).'/'.$countMatches.') - '.$urlSub.PHP_EOL;

					if (!in_array($urlSub, $db[$urlHost]['loc'])) {
						array_push($db[$urlHost]['loc'], $urlSub);
					} else {
						echo 'Main - already in loc db.'.PHP_EOL;
						echo PHP_EOL.'------------------------------------ ('.$urlHost.')'.PHP_EOL;
						$urlTrySub = False;
					}

				} elseif (substr($urlSub, 0, 4) == 'http' || substr($urlSub, 0, 2) == '//') {

					/* Si url commence par // remplacer par http:// */
					if (substr($urlSub, 0, 2) == '//') {
						$urlSub = 'https:'.$urlSub;
					}

					/* Enregistrer comme url externe */
					echo 'GET: ('.($key + 1).'/'.$countMatches.') - '.$urlSub.PHP_EOL;

					if (!in_array($urlSub, $db[$urlHost]['ext'])) {
						array_push($db[$urlHost]['ext'], $urlSub);
						/* Créer (si existe pas) et ajouter à la branche du domaine externe */
						if (!isset($db[parse_url($urlSub, PHP_URL_HOST)])) {
							$db[parse_url($urlSub, PHP_URL_HOST)] = array(
								'crawl' => False,
								'inf' => array(),
								'loc' => array(),
								'ext' => array(),
							);
							if (preg_match("/((?<!www|fr|en)\.(:?wordpress\.org|linkedin\.com|wikipedia\.org|facebook\.com))/i", parse_url($urlSub, PHP_URL_HOST))) {
								$db[parse_url($urlSub, PHP_URL_HOST)]['crawl'] = True;
							}
						}
						array_push($db[parse_url($urlSub, PHP_URL_HOST)]['loc'], $urlSub);

					} else {
						echo 'Main - already in ext db.'.PHP_EOL;
						echo PHP_EOL.'------------------------------------ ('.$urlHost.')'.PHP_EOL;
						$urlTrySub = False;
					}
				} else {
					/* url invalide */
					$urlTrySub = False;
				}


				if ($urlTrySub == True) {
					$urlSubHeader = get_headers($urlSub, true);

					/* Si redirection, la mémoriser comme destination en + */
					if (isset($urlSubHeader['Location'])) {
						echo 'REDIRECT:'.PHP_EOL;
	
						/* Si il y a 1 redirection */
						if (!is_array($urlSubHeader['Location'])) {
							$value = $urlSubHeader['Location'];
							$urlSubHeader['Location'] = array();
							array_push($urlSubHeader['Location'], $value);
						}
	
						foreach ($urlSubHeader['Location'] as $key => $value) {
	
							echo ' - '.$value.PHP_EOL;
	
							if (substr($value, 0, 1) == '/') {
								$value = 'https://'.$urlHost.$value;
								
								if (!in_array($value, $db[$urlHost]['loc'])) {
									array_push($db[$urlHost]['loc'], $value);
								} else {
									echo 'Sub - already in loc db.'.PHP_EOL;
								}

							} else {
								if (!in_array($value, $db[$urlHost]['ext'])) {
									array_push($db[$urlHost]['ext'], $value);
									/* Créer (si existe pas) et ajouter à la branche du domaine externe */
									if (!isset($db[parse_url($value, PHP_URL_HOST)])) {
										$db[parse_url($value, PHP_URL_HOST)] = array(
											'crawl' => False,
											'inf' => array(),
											'loc' => array(),
											'ext' => array(),
										);
									}
									array_push($db[parse_url($value, PHP_URL_HOST)]['loc'], $value);
								} else {
									echo 'Sub - already in ext db.'.PHP_EOL;
								}
							}
						}
					}
					echo PHP_EOL.'------------------------------------ ('.$urlHost.')'.PHP_EOL;
				}
			}
			
		} else {
			echo '[!] RIP, reponse_code: '.$urlHeader['reponse_code'].PHP_EOL;
			echo PHP_EOL.'------------------------------------ ('.$urlHost.')'.PHP_EOL;
		}

		/* Ajouter à l'entrée du ndd, que l'on a déjà crawler (ça en fait de la natation) */
		if ($db[$urlHost]['crawl'] != True) {
			$db[$urlHost]['crawl'] = True;
		}
		
		/* Supprimer entrée pété */
		if (isset($db['']['crawl'])) {
			echo 'DELETE "" HOST'.PHP_EOL;
			unset($db['']);
		}

		/* print_r($db);*/
		/* Sauvegarder le json */
		file_put_contents('db.json', json_encode($db, JSON_PRETTY_PRINT));
		return($db);

	}





	function parseHeaders($headers) {

		$head = array();
		foreach($headers as $k => $v) {

			$t = explode(':', $v, 2);
			if (isset($t[1])) {
				$head[trim($t[0])] = trim($t[1]);

			} else {

				$head[] = $v;
				if (preg_match("#HTTP/[0-9\.]+\s+([0-9]+)#", $v, $out)) {
					$head['reponse_code'] = intval($out[1]);
				}
			}
		}
		return($head);
	}