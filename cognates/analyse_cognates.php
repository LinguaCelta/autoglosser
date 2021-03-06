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

// This script generates CS and trigger info.

if (empty($filename))
{
	include("includes/fns.php");
	include("/opt/autoglosser/config.php");
	list($chafile, $filename, $utterances, $words, $cgfinished)=get_filename();
}

// Uncomment for testing of portions of the pipeline.
//$cognates=$filename."_diana";

//$fp=fopen("cognates/diana/".$filename."_cog.txt", "w") or die("Can't create the file");

$sql_t=query("select spkturn, clspk from $cognates group by spkturn, clspk order by spkturn, clspk");  // Get all the speaker turns and place them in order.
while ($row_t=pg_fetch_object($sql_t))
{
	$spkturn=$row_t->spkturn;
	$clspk=$row_t->clspk;
	$tally[$spkturn]=$clspk;  // Tally how many clauses (value) are in each speaker turn (key).
}

//print_r($tally);
// Get all the speaker turns and place them in order.
$sql1=query("select spkturn, clspk from $cognates group by spkturn, clspk order by spkturn, clspk");  // Get all the speaker turns and place them in order.
while ($row1=pg_fetch_object($sql1))
{
	$spkturn=$row1->spkturn;
	$clspk=$row1->clspk;

	$sql3=query("select * from $cognates where spkturn=$spkturn and clspk=$clspk order by clause_id");
	while ($row3=pg_fetch_object($sql3))
	{
		$utt=$row3->utterance_id;
		$minloc=$row3->minloc;
		$maxloc=$row3->maxloc;
		$speaker=$row3->speaker;
		$t=unserialize($row3->t_ser);  // Remember that the figure in the T array refers to the location, which will be the location in the original utterance.  FIX - this should reflect the location in the clause.
		$nt_lg=unserialize($row3->nt_lg_ser);  // The count of the different non-T languages in the clause.
		$surface=$row3->surface;
		$new=$row3->newturn;
		$f_lg=$row3->f_lg;  // Language of the first non-T in the clause.
		$p_lg=$row3->p_lg;  // Language of the last non-T in the clause.
		$clause_id=$row3->clause_id;  // Number of this clause.
		$nextcl=$clause_id+1;  // Number of the next clause.
		$prevcl=$clause_id-1;  // Number of the previous clause.
		$nt_sum=(!empty($nt_lg)) ? array_sum($nt_lg) : 0;  // Number of non-T words in the clause - set it to 0 if the non-T array is empty.
		
		//print_r($t);
		//print_r($nt_lg);
		//echo $nt_sum."\n";
		
		$sql4=query("select * from $cognates where clause_id=$nextcl");
		while ($row4=pg_fetch_object($sql4))
		{
			$next_f_lg=$row4->f_lg;  // Language of the first non-T in the next clause.
			$nextnew=$row4->newturn;
			$next_nt_lg=unserialize($row4->nt_lg_ser);  // The count of the different non-T languages in the next clause.
		}
		
		$sql5=query("select * from $cognates where clause_id=$prevcl");
		while ($row5=pg_fetch_object($sql5))
		{
			$prev_p_lg=$row5->p_lg;  // Language of the last non-T in the previous clause.
			$prevnew=$row5->newturn;
			$prev_nt_lg=unserialize($row5->nt_lg_ser);  // The count of the different non-T languages in the previous clause.
		}
	}

	// ****************************
	// External count (interclausal) - is there a codeswitch between the last non-T word of a clause and the first word of the next clause?
	
	// Algorithm
	// get the language of the last non-T in this clause (call it p_lg)
	// get the language of the first non-T in the next clause (call it f_lg)
		// if f_lg != p_lg, then S, else NS
		// if this clause contains T, then T, else NT
	
	if ($p_lg!=$next_f_lg)  // If there is an external codeswitch (a switch in language between this clause and the next) ...
	{
		$external=(empty($t)) ? "SNT" : "ST";  // ... and if the t array is empty (there are no triggers), mark SNT, otherwise mark ST
	}
	else
	{
		$external=(empty($t)) ? "NSNT" : "NST";  // ... but if there is no codeswitch, and the t array is empty (there are no triggers), mark NSNT, otherwise mark NST
	}

	// Tidy up ...
	$external=($tally[$spkturn]<2) ? "---" : $external;  // Remove the external type marker when there is only one clause in the speaker turn.  // CHECK - Next line will cover this anyway?
	$external=($nextnew=='new') ? "---" : $external;  // Remove the external type marker on the last clause in the speaker turn.
	$external=(empty($nt_lg) or empty($next_nt_lg)) ? "---" : $external;  // Remove the external type marker when there are only Ts in this clause or the next.

	// ****************************
	// Backward external count (interclausal) - is there a codeswitch between the first non-T word of this clause and the last word of the previous clause?
	
	// Algorithm
	// get the language of the last non-T in the previous clause (call it p_lg)
	// get the language of the first non-T in this clause (call it f_lg)
		// if f_lg != p_lg, then S, else NS
		// if this clause contains T, then T, else NT

	if ($prev_p_lg!=$f_lg)  // If there is an externalb codeswitch (a switch in language between this clause and the previous) ...
	{
		$externalb=(empty($t)) ? "SNT" : "ST";  // ... and if the t array is empty (there are no triggers), mark SNT, otherwise mark ST
	}
	else
	{
		$externalb=(empty($t)) ? "NSNT" : "NST";  // ... but if there is no codeswitch, and the t array is empty (there are no triggers), mark NSNT, otherwise mark NST
	}

	// Tidy up ...
	$externalb=($tally[$spkturn]<2) ? "---" : $externalb;  // Remove the externalb type marker when there is only one clause in the speaker turn.  // CHECK - Next line will cover this anyway?
	$externalb=($new=='new') ? "---" : $externalb;  // Remove the externalb type marker on the first clause in the speaker turn.
	$externalb=(empty($nt_lg) or empty($prev_nt_lg)) ? "---" : $externalb;  // Remove the externalb type marker when there are only Ts in this clause or the previous.

	// ****************************
	// Internal count (intraclausal) - is there a codeswitch anywhere within the clause?
	
	// Algorithm
	// if there is more than one language in this clause, then S, else NS
		// if this clause contains T, then T, else NT
		
	if (count($nt_lg)>1) // If there is more than one language in the clause (an internal codeswitch) ...
	{
		$internal=(empty($t)) ? "SNT" : "ST"; // ... and if the t array is empty (there are no triggers), mark SNT, otherwise mark ST
	}
	else
	{
		$internal=(empty($t)) ? "NSNT" : "NST"; // ... but if there is no codeswitch, and the t array is empty (there are no triggers), mark NSNT, otherwise mark NST
	}
	
	// Tidy up ...
	$internal=(empty($nt_lg)) ? "---" : $internal;  // Remove the internal type marker when there are only Ts in the clause.
	$internal=($nt_sum<2) ? "---" : $internal;  // Remove the internal type marker when there is only one non-T in the clause.
	
	// ****************************
	// Remove external codeswitches when there is an internal codeswitch in the clause.
	// Now revoked, because MLF is being used instead.
	//$external=($internal=="SNT" or $internal=="ST") ? "---" : "$external";
	
	// ****************************
	// Write out the  check file if desired.

	if ($new=='new')  // Add blank lines to show changes in speech-turn.
	{
		//fwrite($fp, "\n\n");  // Add blank lines to delineate speaker turns.
		echo "\n\n";
	}

	//fwrite($fp, $spkturn.", ".$clspk."\t".$utt.", ".$minloc."-".$maxloc."\t".$speaker."\t".$surface."\t".$external."\t".$externalb."\t".$internal."\n");  // Write out the clauses.
	echo $speaker.": ".$surface." - ".$external." // ".$externalb." // ".$internal."\n";
	
	// No need to include the $externalb entries - they just confuse people.
	//fwrite($fp, $spkturn.", ".$clspk."\t".$utt.", ".$minloc."-".$maxloc."\t".$speaker."\t".$surface."\t".$external."\t".$internal."\n");  // Write out the clauses.
	//echo $speaker.": ".$surface." - ".$external." // ".$internal."\n";
	
	$write1=query("update $cognates set external='$external', externalb='$externalb', internal='$internal' where spkturn=$spkturn and clspk=$clspk");
	
	unset($verb);
}

//fclose($fp);

?>