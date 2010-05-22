<?php

/*
Copyright Kevin Donnelly 2009.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$lines=file($chafile,FILE_SKIP_EMPTY_LINES);
       
foreach ($lines as $line_num => $line)
{

    //echo $line_num.": <b>".htmlspecialchars($line)."</b><br/><br/>";

	// In [=! laughs], laughs is counted as a token - attach a tilde to it to allow dbslotfix to ignore it
	$line=preg_replace("/(\[=\!)\s(\w+\])/", "$1 ~$2", $line);  // [=! laughs]
	$line=preg_replace("/(\[=\!)\s(yawns)/", "$1 ~$2", $line);  // [=! yawns]
	$line=preg_replace("/(\[=\!)\s(quietly)/", "$1 ~$2", $line);  // [=! quietly]
	// We need to remove this tilde from utterances table after dbslotfix is run

	// Sort out punctuation
	$line=preg_replace("/(\s)<(\w+)/", "$1< $2", $line);  // split off opening angle bracket
	$line=preg_replace("/(\s)<(\()/", "$1< $2", $line);  // to catch < before a bracket, eg <(ba)sai,<(doe)s, <(dy)dy, <(rh)aid, <(wr)tho, <(y)dy, <(y)r
	$line=preg_replace("/(\w+)>(\s)/", "$1 >$2", $line);  // split off closing angle bracket; also need to catch > after a non-abc item, eg &ə> - how? omit for now
	$line=preg_replace("/(\w+)](\s)/", "$1 ]$2", $line);  // split off closing square bracket after the initial [=! or [=?
	$line=preg_replace("/(\.+)\.(\s)/", "$1 .$2", $line);  // split period from +...
	$line=preg_replace("/(\.+)\?(\s)/", "$1 .$2", $line);  // split qmark from +..?
	$line=preg_replace("/(\/?\/)\./", "$1 .", $line);  // split period from +/. and  +"/. and +//.
	$line=preg_replace("/(\")\./", "$1 .", $line);  // split period from +".
	$line=preg_replace("/(\+\!)\?/", "$1 ?", $line);  // split qmark from +!?

	// Apparent transcription errors
	$line=preg_replace("/(\d)\./", "$1 .", $line);  // split period from a preceding @1 or @2; examples seem to be an errors - usually the period has a space between it and the last word of the utterance
	$line=preg_replace("/(\d)\[/", "$1 [", $line);  // split an opening square bracket from the preceding tag
	$line=preg_replace("/(\%gls:\t)\s/", "$1", $line);  // remove errant space from beginning of gloss lines if it occurs
	//$line=preg_replace("/\s+/", "\s", $line);  // remove errant space from beginning of gloss lines if it occurs

	// Collect Welsh utterances - uncomment the commented lines to get some help with debugging

    if (preg_match("/^\*/", $line))
    {
		//echo "This is the Welsh.<br/>";
        $welsh_line=preg_split('/:\t/', $line);
        $speaker=preg_replace("/\*/", "", $welsh_line[0]);
        $rest=$welsh_line[1];
        //echo "Speaker: ".htmlspecialchars($speaker)."<br/>";
        
        list($welsh, $timing)=explode('', $rest); //NAK is Unicode 0015
        
        /*
        $welsh_bits=explode(' ', $welsh);
        array_pop($welsh_bits);
        $i=1;
        foreach ($welsh_bits as $welsh_value)
        {
            echo "The word in ".$i." (total: ".count($welsh_bits).") is: ".htmlspecialchars($welsh_value)."<br>";
            $i=++$i;
        }
        */
        
		$line_num=$line_num +1;
		echo $line_num.": ".$timing."<br/>";

		if (isset($timing))
		{
			if (preg_match('/snd/', $timing))
			{
				list($snd, $chafile, $milli)=explode('"', $timing);
				list($nowt, $durbegin, $durend)=explode('_', $milli);
				$duration=$durend-$durbegin;
			}
			else
			// On some lines the marker %snd may be missing
			{
				list($durbegin, $durend)=explode('_', $timing);
				$duration=$durend-$durbegin;
			}
		}
		else
		{
			// On some lines the sound bullet is completely missing
			$durend=0;
			$durbegin=0;
			$duration=0;
		}
		
        //echo "<br/>Duration of this utterance: ".$durbegin." to ".$durend." (".$duration.")<br/><br/>";

        $speaker=trim(pg_escape_string($speaker));
        $welsh=trim(pg_escape_string($welsh));
		$welsh=preg_replace("/\s+/", " ", $welsh);
		$chafile=trim(pg_escape_string($chafile));
        $line_num=$line_num + 1;

        $sql="insert into $utterances (speaker, duration, welsh, line_num, chafile, durbegin, durend) values ('$speaker',
'$duration', '$welsh', '$line_num', '$chafile', '$durbegin', '$durend')";
        $result=pg_query($db_handle,$sql) or die("Can't insert the items");

    }
    
	// Collect glosses

    elseif (preg_match("/^%gls/", $line))
    {
        //echo "This is a tagged gloss.<br/>";
        $gloss=preg_split('/:\t/', $line);
        $gloss=$gloss[1];
        
        /*
        echo $gloss."<br/>";
        $gloss_bits=explode(' ', $gloss);
        $i=1;
        foreach ($gloss_bits as $gloss_value)
        {
            echo "The word in ".$i." (total: ".count($gloss_bits).") is: ".htmlspecialchars($gloss_value)."<br>";
            $i=++$i;
        }
        */
        $gloss=preg_replace('/xx/', ' ', $gloss);
        $gloss=preg_replace('/xxx/', ' ', $gloss);
        $gloss=trim(pg_escape_string($gloss));

        $sql="update $utterances set gloss='$gloss' where utterance_id=currval('".$utterances."_utterance_id_seq')";
        $result=pg_query($db_handle,$sql) or die("Can't insert the items");

    }
    
    // Collect English interpretations
    
    elseif (preg_match("/^%eng/", $line))
    {
        //echo "This is the English.<br/>";
        $english=preg_split('/:\t/', $line);
        $english=$english[1];
        //echo $english."<br/><hr/>";
        
        $english=trim(pg_escape_string($english));
        
        $sql="update $utterances set english='$english' where utterance_id=currval('".$utterances."_utterance_id_seq')";
        $result=pg_query($db_handle,$sql) or die("Can't insert the items");
        
    }
    
    // Collect comments, if any.  FIXME: In a series of comments, the last one will overwrite previous ones.

	elseif (preg_match("/^@Comment/", $line) or preg_match("/^%com/", $line) )
    {
        //echo "This is a comment line.<br/>";
        $comment=preg_split('/:\t/', $line);
        $comment=$comment[1];
        //echo $comment."<br/><hr/>";
        
        $comment=trim(pg_escape_string($comment));
        
        $sql="update $utterances set comment='$comment' where utterance_id=currval('".$utterances."_utterance_id_seq')";
        $result=pg_query($db_handle,$sql) or die("Can't insert the items");
        
    }

	unset($durbegin, $durend, $duration);
     
}

?>