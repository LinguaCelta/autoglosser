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

if (empty($filename))
{
	include("includes/fns.php");
	include("/opt/autoglosser/config.php");
	list($chafile, $filename, $utterances, $words, $cgfinished)=get_filename();
}

// If this script is called standalone, the language tagging will be faulty because $mflg is not set (calling it via import_only.php is OK, because then the variable is passed in from cgimport.php).
if (!isset($mflg))
{

	// Get the most frequent and less frequent languages from the file header. 
	$lines=file("outputs/$filename/$filename.header");  // Open the header file.
	foreach ($lines as $line)
	{
		if (preg_match("/@Languages:\t/", $line))
		{
			$lgline=trim(preg_replace("/@Languages:\t/","", $line));
			$filelgs=explode(", ", $lgline);
			//print_r($filelgs);
		}
	}

	// This adds the most-frequent language to the correct tag array in includes/fns.php.
	switch ($filelgs[0]) 
	{
		case "cym":
			array_push($cylg, "");
			break;
		case "eng":
			array_push($enlg, "");
			break;
		case "spa":
			array_push($eslg, "");
			break;
	}

	// This adds the second most-frequent language to the correct tag array in includes/fns.php.
	switch ($filelgs[1]) 
	{
		case "cym":
			array_push($cylg, "s");
			break;
		case "eng":
			array_push($enlg, "s");
			break;
		case "spa":
			array_push($eslg, "s");
			break;
	}

	// Set up the variables for more-frequent and less-frequent languages for use by rewrite_utterances.php and tex/generate_expex.php.  The most frequent language is the one that is unmarked in the text, the less frequent language is the one that will be unmarked with a precode at the beginning of the line.  Note that this system only accommodates two languages - we probably need to rethink for three or more.
	$mflg=$filelgs[0];
	$lflg=$filelgs[1];
}

echo $mflg."\n";
echo $lflg."\n";


echo "*\n*\nCreating the $words table\n*\n*\n";
include("create_cgwords.php");

$fp = fopen("outputs/".$filename."/".$utterances."_rewritten.txt", "w") or die("Can't create the file");

$sql="select * from $utterances order by utterance_id";
$result=pg_query($db_handle,$sql) or die("Can't get the items");
while ($row=pg_fetch_object($result))
{
	echo $oldutt="(".$row->utterance_id.") ".$row->surface."\n";
	echo $newutt=lineclean_surface($row->surface)."\n\n";

	fwrite($fp, $oldutt);
	fwrite($fp, $newutt);

	$precode=$row->precode;
	$surface_bits=explode(' ', $newutt);
    $i=1;   
    foreach ($surface_bits as $surface_value)
    {
		// Review this - it may be better to generate a fixed langid rather than copy the one from the text, ie generate 'spa' for 3, es, spa, etc.
		if  (preg_match("/@/", $surface_value))     
		{
			list($surface_word, $langid)=explode('@', $surface_value);  // Siarad: hwyrach@1
			$langid=preg_replace("/s:/","", $langid);  // Patagonia: oh@s:cy&es - ie, remove s: as well as split at @
/*
			// New CLAN default
			if (preg_match("/^s$/", $langid))  // What is this condition for?
			{
				//$langid=($precode==$lflg) ? $mflg : $lflg;
				if ($precode =="eng")
				{
					$langid="eng";
				}
				elseif ($precode=="spa")
				{
					$langid="spa";
				}
				elseif ($precode=="cym")
				{
					$langid="cym";
				}
			}
*/
		}
		elseif(preg_match("/(\.|\?|!)/", $surface_value)) 
		{
			$surface_word=$surface_value;
			$langid="999";
		} 
		else
		{
            // No langid tag is on the word; in this case, the language will be the one marked as blank ("") in the language arrays at the top of fns.php.
			$surface_word=$surface_value; 
			//$langid="";
			// changes to handle new CLAN default
			// replace above line as follows:
			//$langid=($precode==$lflg) ? $lflg : $mflg;
			if ($precode =="eng")
			{
				$langid="eng";
			}
			elseif ($precode=="spa")
			{
				$langid="spa";
			}
			elseif ($precode=="cym")
			{
				$langid="cym";
			}
			elseif ($precode=="")
			{
				//$langid="spa";  // Adjust this to give the default language.
				$langid=$mflg;  // Adjust this to give the default language.
			}
		} 

        $surface_word=trim(pg_escape_string($surface_word)); 
        $surface_word=wordclean_surface($surface_word);
		//echo $row->utterance_id." - ".$i." - ".$surface_word." - ".$langid." - ".$row->speaker." - ".$row->chafile."\n\n";
        $sql_w="insert into $words (utterance_id, location, surface, langid, speaker, filename) values ('$row->utterance_id', '$i', '$surface_word', '$langid', '$row->speaker', '$row->filename')";
        $result_w=pg_query($db_handle,$sql_w) or die("Can't insert the items");
        $i=++$i; 
    }

    $tiers=file("outputs/".$filename."/".$filename."_tiers.txt", FILE_IGNORE_NEW_LINES);
   //if (count($tiers)>1)  // not a good idea if only one tier , eg mor, exists; better to do it by looking at each tier, as with gra
    //{
        //$tiers=array_diff($tiers, array(gra));
        foreach ($tiers as $tier)
        {
            if ($tier=='gls' or $tier=='mor' or $tier=='glo')  // No point in importing %eng, since the slots will not align
            {
                $tier=trim($tier);
                $lineclean_tier="lineclean_".$tier;
                $wordclean_tier="wordclean_".$tier;
                echo $treated=$lineclean_tier($row->$tier)."\n";  // This function applies changes to the whole line
                $bits=explode(' ', $treated);
                $j=1;
                foreach ($bits as $value)
                {
                    $value=trim(pg_escape_string($value));
                    //$value=wordclean_tier($value);  // This function applies changes to the individual word entries
                    //echo $j." (of ".count($gloss_bits)."): ".htmlspecialchars($gloss_value)."<br>";       
                    $sql_g="update $words set $tier='$value' where utterance_id=$row->utterance_id and location=$j";
                    $result_g=pg_query($db_handle,$sql_g) or die("Can't insert the items");
                    $j=++$j;    
                }
                unset($tier);
            }
            elseif ($tier=='gra')  // With the %gra tier, we need something more complex than a simple lineclean.
            {
                include("tiers/gra.php");
            }
        } 
    //}
	unset($newutt);

}

fclose($fp);

?>
