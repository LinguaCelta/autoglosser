<?php

/* 
*********************************************************************
Copyright Kevin Donnelly 2010, 2011.
kevindonnelly.org.uk
This file is part of the Bangor Autoglosser.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License and the GNU
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

if (!isset($chain))
{
	include("includes/fns.php");
	include("/opt/autoglosser/config.php");
}

// $filename="histcorpus/groniosaw_split.txt";
// $utterances="groniosaw_cgutterances";
// $words="groniosaw_cgwords";
// $cgfinished="groniosaw_cgfinished";
$filename="histcorpus/ryan.txt";
$utterances="ryan_cgutterances";
$words="ryan_cgwords";
$cgfinished="ryan_cgfinished";

if (empty($mflg))
{
	$mflg="cym";  // If calling this script stand-alone, specify the default language here.
}

//$fp = fopen("outputs/groniosaw/groniosaw.tex", "w") or die("Can't create the file");
$fp = fopen("outputs/ryan/ryan.tex", "w") or die("Can't create the file");

$lines=file("tex/tex_header.tex");  // Open header file containing LaTeX markup to set up the document.
foreach ($lines as $line)
{
	if (preg_match("/filename/", $line))  // replace the holder in the TeX file with the name of the conversation
	{
		$line=preg_replace("/filename/", "$capfile", $line);
	}
	else
	{
		$line=$line;
	}
	//echo $line."\n";
	fwrite($fp, $line);
}

$surface='';
$auto='';
// An empty variable has to be set up here, otherwise the concatenation $surface.=$row_w->surface." "; below will result in the first line of the text having a preceding dot.  A similar point applies to $auto, which will otherwise attach the last gloss in the file to the first item.

$sql_s="select * from $utterances order by utterance_id";
$result_s=pg_query($db_handle,$sql_s) or die("Can't get the items");
while ($row_s=pg_fetch_object($result_s))
{	
	$chat=tex_surface($row_s->surface);
	
    $sql_w="select * from $words where utterance_id=$row_s->utterance_id order by location";
	$result_w=pg_query($db_handle,$sql_w) or die("Can't get the items");
	while ($row_w=pg_fetch_object($result_w))
	{
		$row_w->surface=tex_surface($row_w->surface);  // comment out _ and % to keep LaTeX happy.

		$surface.=$row_w->orig_surface." ";  // Note that you  need to set up an empty $surface first - see above.
		
		$row_w->auto=tex_auto($row_w->auto);
		//$row_w->auto=tex_pos_colour($row_w->auto);  // Uncomment to get colour-coded POS tags.
		$auto.=$row_w->auto." "; // Note that you  need to set up an empty $auto first - see above.
	}

	$begingl="\ex\n\begingl\n";
	fwrite($fp, $begingl);
	
	//$wchat="\glpre ".$chat." //\n";
	//echo $wchat."\n";
	//fwrite($fp, $wchat);

	if ($surface!='')  // Provided there is verbal content in the line ...
	{
		$wsurface="\gla ".$surface." //\n";
		echo $wsurface."\n";
		fwrite($fp, $wsurface);
		
		$wauto="\glb ".$auto." //\n";  // Autogloss tier.
		echo $wauto."\n";
		fwrite($fp, $wauto);
		
		$endgl="\endgl\n\\xe\n";
		fwrite($fp, $endgl);
	}
	else  // ... if not, just end the example.
	{
		$endgl="\endgl\n\\xe\n";
		fwrite($fp, $endgl);
	}

	fwrite($fp, "\n");

	unset($chat, $surface, $auto, $wchat, $wsurface, $wauto);
}

$lines=file("tex/tex_footer.tex");  // Open footer file.
foreach ($lines as $line)
{
	fwrite($fp, $line);
}

fclose($fp);

?>
