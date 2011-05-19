<?php

// This script marks verbs in an utterance with a clause marker, which can then be used to segment the utterance into clauses.

if (empty($filename))
{
	include("includes/fns.php");
	include("/opt/autoglosser/config.php");
	list($chafile, $filename, $utterances, $words, $cgfinished)=get_filename();
}

$sql_clear=query("update ".$filename."_sampleclauses set clause=''");  // Remove previous clause-splitting entries

$sql_mark=query("update ".$filename."_sampleclauses set clause='c' where auto~'\\\.V\\\.'");  // Put a clause-marker against verb entries

?>