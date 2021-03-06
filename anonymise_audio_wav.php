<?php

/* 
*********************************************************************
Copyright Kevin Donnelly 2010, 2011.
kevindonnelly.org.uk
This file is part of the Bangor Autoglosser.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License or the GNU
Affero General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option)
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
and the GNU Affero General Public License along with this program.
If not, see <http://www.gnu.org/licenses/>.
*********************************************************************
*/

// This script uses the sound-bullet information in the _utterances table to silence the audio where utterances are marked www (ie no permission to distribute, or contain pseudonymised names in the text.

if (empty($filename))
{
	include("includes/fns.php");
	include("/opt/autoglosser/config.php");
	list($chafile, $filename, $utterances, $words, $cgfinished)=get_filename();
}
$silenced=$filename."_silenced";  // Name for the output file.
$pseud=$filename."_pseud";  // Name for the pseudonym file.
$sox=$filename."_sox";  // Name for the silence location table.
$snd_frmt="wav"; 

$inpath="/home/kevin/sdb7/miami_wav_to_be_silenced";
$outpath="/home/kevin/sdb7/miami_wav_silencedfiles";

/*
drop_existing_table($sox);

$pad='';
$trim='';
$splice='';

// Get any utterances marked www in the transcription.
$sql_www=query("create table $sox as select utterance_id, durbegin, durend, duration from $utterances where surface ~ 'www' order by utterance_id");
*/

/*-------------------------------------------
// For pseudonyms too:

// Get the utterances where pseudonymised names occur.  This uses a table with the pseudonyms listed, which needs to be prepared
// beforehand by referring to the pseudonym file.  If no list of pseudonymisations has been retained, just put all the names listed by
// the autoglosser into a table:
// create table myfile_pseud as select surface, count(surface) from myfile_cgwords where auto='name' group by surface
// and then edit that to remove any entries you don't need to worry about (eg place-names).

$sql_t=query("insert into $sox select utterance_id, durbegin, durend, duration from $utterances where utterance_id in (select distinct utterance_id from $words where surface in (select surface from $pseud order by surface) order by utterance_id)");

------------------------------------------*/
/*
$durbegin_prev=0;
$durend_prev=0;

// Tidy the generated table of places to silence.
$sql_d=query("select * from $sox order by utterance_id");
while ($row_d=pg_fetch_object($sql_d))
{
	// Delete utterances that are fully overlapped by another (otherwise you get a pad error).
	if ($row_d->durbegin>$durbegin_prev and $row_d->durend<$durend_prev)  
	{
		$sql_out=query("delete from $sox where utterance_id=$row_d->utterance_id");
	}
	
	// If the startpoint of one utterance is less than the endpoint of the previous one, move the startpoint past the endpoint, and adjust
	// the duration (otherwise you get a splice error).   You will probably get a warning like: 
	// sox WARN splice: Input audio too short; splices not made: 46
	// but the audio will probably sound alright.  According to Ulrich Klauer, this is a bug in splice - "splice doesn't like two positions that
	// are very close to each other, and ignores all following positions.  It isn't too apparent since the splicing is optional, and only there
	// to reduce clicks at the silence boundaries, but it isn't done from this point to the end."
	if ($row_d->durbegin<$durend_prev)  
	{
		echo $row_d->utterance_id."\n";
		$sql_fixbeg=query("update $sox set durbegin=($durend_prev+1) where utterance_id=$row_d->utterance_id");
		$sql_fixdur=query("update $sox set duration=($row_d->durend-$durend_prev-1) where utterance_id=$row_d->utterance_id");
	}
	$durbegin_prev=$row_d->durbegin;
	$durend_prev=$row_d->durend;
}

// Remove entries where the duration is negative, or pad will complain.
$sql_n=query("delete from $sox where duration<=0;");
*/

// Loop through the tidied result set.
$sql="select * from $sox order by utterance_id";
$result=pg_query($db_handle,$sql) or die("Can't get the items");
while ($row=pg_fetch_object($result))
{
	// Convert to seconds from CLAN's milliseconds, and format for passing to bash.
	$durbegin=$row->durbegin/1000;
	$durend=$row->durend/1000;
	$duration=$row->duration/1000;
	$pad.=$duration."@".$durbegin." \\\n";
	$trim.="trim 0 ".$durend." ".$duration." \\\n";
	$splice.=$durbegin." ".$durend." \\\n";
}

//$sox_command="sox $inpath/$filename.$snd_frmt $outpath/$silenced.$snd_frmt \\\npad \\\n".$pad.$trim."splice \\\n".$splice;
// Splice causes problems, and apparently isn't really needed.
$sox_command="sox $inpath/$filename.$snd_frmt $outpath/$silenced.$snd_frmt \\\npad \\\n".$pad.$trim;

//echo $sox_command;

exec("$sox_command");

?>
