<?php

/*
This script converts chat files tagged with @0, @1, @2, @3 to the new CLAN default with precodes.  The conventions used for this are as follows (for Spanish and English):
(1) Use the lgprofile table to find all utterances where every item is tagged as eng.
(2) Remove all @2 tags and mark the utterance with a precode [- en].
(3) For all other utterances: (a) remove all @3 tags from spa items; (b) convert all @2 tags to @s on eng items; (c) convert all @0 tags to @s:spa&eng.
The output file will need the headers added manually, and the languages need to be listed (more frequent language first):
@Languages:	spa, eng
*/

if (empty($filename))
{
	include("includes/fns.php");
	include("/opt/autoglosser/config.php");
	list($chafile, $filename, $utterances, $words, $cgfinished)=get_filename();
}
$profiletable=$filename."_lgprofile";
//$converted=$filename."_converted";

$fp = fopen("outputs/".$filename."/".$filename."_converted.txt", "w") or die("Can't create the file");

$sql1="select utterance_id, lgprofile from $profiletable where lgprofile !~'(0|3)' and lgprofile != ''";
// The above would need to be changed for alphabetic language tags (en, es, spa, etc)
$result1=pg_query($db_handle,$sql1) or die("Can't get the items");
while ($row1=pg_fetch_object($result1))
{
	$profiles[]=$row1->utterance_id;
}

$sql3="select * from $utterances order by utterance_id";
$result3=pg_query($db_handle,$sql3) or die("Can't get the items");
while ($row3=pg_fetch_object($result3))
{
	$surface=$row3->surface;
	if (in_array("$row3->utterance_id", $profiles))
	{
		$surface="[- eng] ".$surface;
		$surface=preg_replace("/@2/", "", $surface);
	}
	else
	{
		$surface=preg_replace("/@3/", "", $surface);
		$surface=preg_replace("/@2/", "@s", $surface);
		$surface=preg_replace("/@0/", "@s:spa&eng", $surface);
	}

	$speech="*".$row3->speaker.":	".$surface." %snd:\"".$row3->filename."\"_".$row3->durbegin."_".$row3->durend."\n";
	fwrite($fp, $speech);

	echo $row3->utterance_id.": ".$surface."\n";

	/* May not be necessary to generate a table.
	$surface=pg_escape_string($surface);
	$sqlnew="insert into $converted(utterance_id, surface, speaker) values($row3->utterance_id, '$surface', '$row3->speaker')";
	$resultnew=pg_query($db_handle,$sqlnew) or die("Can't get the items");
	*/
}

fclose($fp);

?>