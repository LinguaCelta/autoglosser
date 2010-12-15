<?php

/*
This script generates a language profile for each utterance - a sequence of the language tags for each item in the utterance.  This works best for the old @0, @1, @2, @3 tags, and less well for the newer alphabetic ones (en, es, spa).
*/

if (empty($filename))
{
	include("includes/fns.php");
	include("/opt/autoglosser/config.php");
	list($chafile, $filename, $utterances, $words, $cgfinished)=get_filename();
}

$profiletable=$filename."_lgprofile";
drop_existing_table($lgprofile);

$sql_table = "
CREATE TABLE sastre1_default (
    utterance_id serial NOT NULL,
    surface text,
    cleaned text,
    lgprofile character varying(200)
);
";
$result_table=pg_query($db_handle, $sql_table);

$sql_pkey = "
ALTER TABLE ONLY ".$profiletable." ADD CONSTRAINT ".$profiletable."_pk PRIMARY KEY (utterance_id);
";
$result_pkey=pg_query($db_handle, $sql_pkey);

$sql3="select * from $utterances order by utterance_id";
$result3=pg_query($db_handle,$sql3) or die("Can't get the items");
while ($row3=pg_fetch_object($result3))
{
	echo $row3->utterance_id.": ".$row3->surface."\n";
	
	$row3->surface=pg_escape_string($row3->surface);
	$sqlutt="insert into $profiletable(utterance_id, surface) values($row3->utterance_id, '$row3->surface')";
	$resultutt=pg_query($db_handle,$sqlutt) or die("Can't get the items");

	$sql2="select * from $words where utterance_id=$row3->utterance_id order by location";
	$result2=pg_query($db_handle,$sql2) or die("Can't get the items");
	while ($row2=pg_fetch_object($result2))
	{	
		// Surface line without markings
		$text.=$row2->surface." ";
		if ($row2->langid=='999')
		{
			$slot='';
		}
		else
		{
			$slot=$row2->langid;
		}
		$slotstring.=$slot;
	}
		
	echo $row3->utterance_id.": ".$text."\n";
	echo $row3->utterance_id.": ".$slotstring."\n";
	
	$text=pg_escape_string($text);
	$sqlslot="update $profiletable set cleaned='$text', lgprofile='$slotstring' where utterance_id=$row3->utterance_id";
	$resultslot=pg_query($db_handle,$sqlslot) or die("Can't get the items");
	
	unset($text, $slotstring);
	echo "\n\n";
}

?>
