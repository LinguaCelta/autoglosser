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

// This script splits the corpus utterances into clauses, and combines them with questionnaire data to give input to feed into Rbrul.

include("includes/fns.php");
include("/opt/autoglosser/config.php");

$output_path="caroline/outputs";
$jumbo_file="jumbo_patagonia_check.csv";
$jumbo_path="caroline";

// Generate default names from the filepath given
list($chafile, $filename, $utterances, $words, $cgfinished)=get_filename();

echo $chafile."\n";
echo $filename."\n";
echo $utterances."\n";
echo $words."\n";
echo $cgfinished."\n";

include("cognates/extend_cgwords.php");

include("cognates/mark.php");

include("cognates/adjust_deletes.php");

include("cognates/adjust_moves.php");

include("cognates/segment.php");  // Can write _split.txt.

include("cognates/reinforcers.php");

//include("cognates/insert_triggers.php");  Use if you want to note codeswitches.

include("cognates/write_rei.php");  // Can write _spk.txt.

//include("cognates/write_cognates.php");  // Can write _spkturn.txt.

include("cognates/analyse_clauses.php");  // Writes _clauses.csv.
//include("cognates/analyse_cognates.php");  // Use if you want to notate codeswitches.

exec("rm $jumbo_path/$jumbo_file");  // Remove the existing jumbo file.
exec("cat $output_path/{$filename}_clauses.csv >> $jumbo_path/$jumbo_file");  // Add all the clauses to one big file.

?>
