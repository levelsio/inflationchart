<? 
	error_reporting(E_ALL);

	date_default_timezone_set('UTC');
	mb_internal_encoding("UTF-8");

	echo "Starting...\n";





	// <rows>
		$dbFile=__DIR__.'/../data/inflationchart.db';
		$dir = 'sqlite:/'.$dbFile;
		$inflationchartDb  = new PDO($dir) or exit(68); /* db erorr */
		$inflationchartDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if (!$inflationchartDb) exit(68); /* db error */
		
		$query = $inflationchartDb->prepare('DELETE from inflationchart');
		$query->execute();

		$feed = 'https://docs.google.com/spreadsheets/d/1xJGrHWj6uO6ykFPvht-RBG5qlLeO0axraxUJ9UzOhFo/export?format=csv&gid=347952624';
		$keys = array();
		$newArray = array();

		echo "Loading ".$feed."..."."\n";

		if(!$data = csvToArray($feed, ',')) {
			echo "Failed to load ".$feed."\n";
			exit(52);
		}
		echo "Loaded!"."\n";
		echo "Importing data..."."\n";


		// Set number of elements (minus 1 because we shift off the first row)
		$count = count($data) - 1;
		//Use first row for names  
		$labels = array_shift($data);  

		foreach ($labels as $label) {
			$keys[] = $label;
		}

		$keys[] = 'id';

		for ($i = 0; $i < $count; $i++) {
			$data[$i][] = $i;
		}

		for ($j = 0; $j < $count; $j++) {
			$d = array_combine($keys, $data[$j]);
			if(!is_array($d)) {
				
				echo $d;
				echo "\n";


				$d=trim($d);
			}
			$newArray[$j] = $d;
		}


		echo "Processing ".count($newArray)." rows..."."\n";
		if(count($newArray)==0) {
			exit(53); /* no data */
		}
		$queries=0;
		$rows=$newArray;
		$i=0;

		$newRows=array();
		foreach($rows as $row) {
			foreach($row as $key => $value) {
				$value=str_replace('Loading...','',$value);
				if(is_numeric(str_replace('$','',str_replace(',','',trim($value))))) {
					$value=str_replace('$','',$value);
					$value=str_replace(',','',$value);
				}
				$value=str_replace('#N/A','',$value);
				$value=str_replace('N/A','',$value);
				$row[$key]=$value;
			}
			array_push($newRows,$row);
		}
		$rows=$newRows;


		foreach($rows as $row) {

			echo json_encode($row);
			echo "\n\n";

			if($i==0) {
				$i++;
				continue;
			}

			$query = $inflationchartDb->prepare('REPLACE INTO inflationchart(epoch,m1,m2,m3,mb,cpi,gdp,income,bigmac,spx,dji,asia,singapore,china,nasdaq,btc,home,rent,food,food_and_home,coffee,oil,lumber,us10y,minwage,gold,copper,silver,eth,tsla,nomadlist,remoteok,makebook,levelsio,population,date,epoch_updated) values (:epoch,:m1,:m2,:m3,:mb,:cpi,:gdp,:income,:bigmac,:spx,:dji,:asia,:singapore,:china,:nasdaq,:btc,:home,:rent,:food,:food_and_home,:coffee,:oil,:lumber,:us10y,:minwage,:gold,:copper,:silver,:eth,:tsla,:nomadlist,:remoteok,:makebook,:levelsio,:population,:date,:epoch_updated);');
			$query->bindValue(':epoch',strtotime($row['date']));
			$query->bindValue(':m1',$row['m1']);
			$query->bindValue(':m2',$row['m2']);
			$query->bindValue(':m3',$row['m3']);
			$query->bindValue(':mb',$row['mb']);
			$query->bindValue(':cpi',$row['cpi']);
			$query->bindValue(':gdp',$row['gdp']);
			$query->bindValue(':income',$row['income']);
			$query->bindValue(':bigmac',$row['bigmac']);
			$query->bindValue(':spx',$row['spx']);
			$query->bindValue(':dji',$row['dji']);
			$query->bindValue(':asia',$row['asia']);
			$query->bindValue(':singapore',$row['singapore']);
			$query->bindValue(':china',$row['china']);
			$query->bindValue(':nasdaq',$row['nasdaq']);
			$query->bindValue(':btc',$row['btc']);
			$query->bindValue(':home',$row['home']);
			$query->bindValue(':rent',$row['rent']);
			$query->bindValue(':food',$row['food']);
			$query->bindValue(':food_and_home',$row['food_and_home']);
			$query->bindValue(':coffee',$row['coffee']);
			$query->bindValue(':oil',$row['oil']);
			$query->bindValue(':lumber',$row['lumber']);
			$query->bindValue(':us10y',$row['us10y']);
			$query->bindValue(':minwage',$row['minwage']);
			$query->bindValue(':gold',$row['gold']);
			$query->bindValue(':copper',$row['copper']);
			$query->bindValue(':silver',$row['silver']);
			$query->bindValue(':eth',$row['eth']);
			$query->bindValue(':tsla',$row['tsla']);
			$query->bindValue(':population',$row['population']);
			$query->bindValue(':nomadlist',$row['nomadlist']);
			$query->bindValue(':remoteok',$row['remoteok']);
			$query->bindValue(':makebook',$row['makebook']);
			$query->bindValue(':levelsio',$row['levelsio']);
			$query->bindValue(':date',$row['date']);
			$query->bindValue(':epoch_updated',time());
			if(!$query->execute()) {
				exit(68); /* db erorr */
			}
			$i++;
		}

		echo "\n\n";
		echo "Imported ".count($rows)." rows"."\n";
		echo "Imported ".number_format($queries)." data points"."\n";
		echo "\n\n";
	// </rows>





	function csvToArrayDev($file) {
		$handle = @fopen( $file, "r");
		if ( !$handle ) {
			throw new \Exception( "Couldn't open $file!" );
		}

		$result = [];
		$first = strtolower( fgets( $handle, 4096 ) );
		$keys = str_getcsv( $first );

		while ( ($buffer = fgets( $handle, 4096 )) !== false ) {
			$array = str_getcsv ( $buffer );
			if ( empty( $array ) ) continue;
			$row = [];
			$i=0;

			foreach ( $keys as $key ) {
				$row[ $key ] = $array[ $i ];
				$i++;
			}

			$result[] = $row;
		}

		fclose( $handle );
		return $result;
	}
	function csvToArray($file,$delimiter) {
		$handle = fopen('php://temp', 'w+');

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $file);
		curl_setopt($curl, CURLOPT_FILE, $handle);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($curl);
		curl_close($curl);

		rewind($handle);

		$i = 0; 
		while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== FALSE) { 
			for ($j = 0; $j < count($lineArray); $j++) { 
				// $arr[$i][$j] = htmlspecialchars(utf8_encode(trim($lineArray[$j])));
				$arr[$i][$j] = $lineArray[$j];
			} 
			$i++; 
		} 
		return $arr; 
	}

	function sortBySubkey(&$array, $subkey="id", $sort_ascending=false) {
		if($sort_ascending==='desc') {
		 	$sort_ascending=false;
		} else if($sort_ascending==='asc') {
		 	$sort_ascending=true;
		}
        
        usort($array, function ($a, $b) use ($subkey) {
             if ($a[$subkey] == $b[$subkey]) return 0;
             return ($a[$subkey] < $b[$subkey]) ? -1 : 1;
         });

         if(!$sort_ascending) $array = array_reverse($array);
	}

	exit(90);

?>
