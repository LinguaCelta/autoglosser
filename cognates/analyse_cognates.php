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

// This script arranges the file in order of speech-turn and clause.

if (empty($filename))
{
	include("includes/fns.php");
	include("/opt/autoglosser/config.php");
	list($chafile, $filename, $utterances, $words, $cgfinished)=get_filename();
}

$cognates=$filename."_cognates";

$fp = fopen("cognates/outputs/".$filename."_cog.txt", "w") or die("Can't create the file");

$sql_t=query("select spkturn, clspk from $cognates group by spkturn, clspk order by spkturn, clspk");  // Get all the speaker turns and place them in order.
while ($row_t=pg_fetch_object($sql_t))
{
	$spkturn=$row_t->spkturn;
	$clspk=$row_t->clspk;
	$tally[$spkturn]=$clspk;  // Tally how many clauses (value) are in each speaker turn (key).
}

//print_r($tally);

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
		$t=unserialize($row3->t_ser);  // Remember that the figure in the T array refers to the location, which will be between minloc and maxloc.
		$nt_lg=unserialize($row3->nt_lg_ser);  // The count of the different languages appearing in the clause.
		$surface=$row3->surface;
		$new=$row3->newturn;
		$f_lg=$row3->f_lg;
		$p_lg=$row3->p_lg;
		$clause_id=$row3->clause_id;  // Number of this clause.
		$nextcl=$clause_id+1;  // Number of the next clause.
		$prevcl=$clause_id-1;  // Number of the previous clause.

		$sql4=query("select * from $cognates where clause_id=$nextcl");
		while ($row4=pg_fetch_object($sql4))
		{
			$next_f_lg=$row4->f_lg;
			$nextnew=$row4->newturn;
		}
		
		$sql5=query("select * from $cognates where clause_id=$prevcl");
		while ($row5=pg_fetch_object($sql5))
		{
			$prev_p_lg=$row5->p_lg;
			$prevnew=$row5->newturn;
		}
		
	}
	
	
	
	// ****************************
	// External count (interclausal) - is there a codeswitch between the last non-T word of a clause and the first word of the next clause?
	
	// Algorithm
	// get the language of the last non-T in this clause (call it p_lg)
	// get the language of the first non-T in the next clause (call it f_lg)
		// if f_lg != p_lg, then S, else NS
		// if this clause contains T, then T, else NT
	
	if ($p_lg!=$next_f_lg)  // If there is an external codeswitch (a switch in language between clauses) ...
	{
		$external=(empty($t)) ? "SNT" : "ST";
	}
	else
	{
		$external=(empty($t)) ? "NSNT" : "NST";
	}
		
	$external=($tally[$spkturn]<2) ? "---" : $external;  // Remove the external type marker when there is only one clause in the speaker turn.
	$external=($nextnew=='new') ? "---" : $external;  // Remove the external type marker on the last clause in the speaker turn.



	// ****************************
	// Backward external count (interclausal) - is there a codeswitch between the first non-T word of this clause and the last word of the previous clause?
	
	// Algorithm
	// get the language of the last non-T in the previous clause (call it p_lg)
	// get the language of the first non-T in this clause (call it f_lg)
		// if f_lg != p_lg, then S, else NS
		// if this clause contains T, then T, else NT


	if ($prev_p_lg!=$f_lg)  // If there is an externalb codeswitch (a switch in language between clauses) ...
	{
		$externalb=(empty($t)) ? "SNT" : "ST";
	}
	else
	{
		$externalb=(empty($t)) ? "NSNT" : "NST";
	}

	$externalb=($new=='new') ? "---" : $externalb;  // Remove the externalb type marker on the first clause in the speaker turn.



	// ****************************
	// Internal count (intraclausal) - is there a codeswitch anywhere within the clause?
	
	// Algorithm
	// if there is more than one language in this clause, then S, else NS
		// if this clause contains T, then T, else NT
		
	if (count($nt_lg)>1) // If there is more than one language in the clause (an internal codeswitch) ...
	{
		$internal=(empty($t)) ? "SNT" : "ST";
	}
	else
	{
		$internal=(empty($t)) ? "NSNT" : "NST";
	}
	
	
	
	// ****************************
	// Write out a check file

	if ($new=='new')  // Add a blank line to show changes in speech-turn.
	{
		fwrite($fp, "\n");  // Add a blank line to delineate speaker turns.
		echo "\n";
	}

	fwrite($fp, $spkturn.", ".$clspk."\t".$utt.", ".$minloc."-".$maxloc."\t".$speaker."\t".$surface."\t".$external."\t".$externalb."\t".$internal."\n");  // Write out the clauses.
	echo $speaker.": ".$surface." - ".$external." // ".$externalb." // ".$internal."\n";
	
	$write1=query("update $cognates set external='$external', externalb='$externalb', internal='$internal' where spkturn=$spkturn and clspk=$clspk");
}

fclose($fp);

?>