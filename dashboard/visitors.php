<?php
$aryConf['data']['Visitors'] = $this->call_API(
                        'VisitsSummary.getVisits', 
			$aryConf['params']['period'], 
			$aryConf['params']['date'],
                        $aryConf['params']['limit']
                );
$aryConf['data']['Unique'] = $this->call_API(
                        'VisitsSummary.getUniqueVisitors',
			$aryConf['params']['period'],
                        $aryConf['params']['date'],
                        $aryConf['params']['limit']
                );
$aryConf['data']['Bounced'] = $this->call_API(
			'VisitsSummary.getBounceCount',
			$aryConf['params']['period'],
                        $aryConf['params']['date'],
                        $aryConf['params']['limit']
		);

$aryConf['title'] = __('Visitors', 'wp-piwik');
include('header.php');
$strValues = $strLabels = $strBounced =  $strValuesU = '';
$intMax = max($aryConf['data']['Visitors']);
while ($intMax % 10 != 0 || $intMax == 0) $intMax++;
$intStep = $intMax / 5;
while ($intStep % 10 != 0 && $intStep != 1) $intStep--;

foreach ($aryConf['data']['Visitors'] as $strDate => $intValue) {
        $strValues .= round($intValue/($intMax/100),2).',';
        $strValuesU .= round($aryConf['data']['Unique'][$strDate]/($intMax/100),2).',';
	$strBounced .= round($aryConf['data']['Bounced'][$strDate]/($intMax/100),2).',';
        $strLabels .= '|'.substr($strDate,-2);
}
$strValues = substr($strValues, 0, -1);
$strValuesU = substr($strValuesU, 0, -1);
$strBounced = substr($strBounced, 0, -1);

$strBase  = 'http://chart.apis.google.com/chart?';
$strGraph = 'cht=lc&amp;';
$strGraph .= 'chg=0,'.round($intStep/($intMax/100),2).',2,2&amp;';
$strGraph .= 'chs=500x220&amp;';
$strGraph .= 'chd=t:'.$strValues.'|'.$strValuesU.'|'.$strBounced.'&amp;';
$strGraph .= 'chxl=0:'.$strLabels.'&amp;';
$strGraph .= 'chco=90AAD9,A0BAE9,E9A0BA&amp;';
$strGraph .= 'chm=B,D4E2ED,0,1,0|B,E4F2FD,1,2,0|B,FDE4F2,2,3,0&amp;';
$strGraph .= 'chxt=x,y&amp;';
$strGraph .= 'chxr=1,0,'.$intMax.','.$intStep;
?>
<div class="wp-piwik-graph-wide">
<img src="<?php echo $strBase.$strGraph; ?>" width="500" height="220" alt="Visits graph" />
</div>
<div class="table">
<table class="widefat wp-piwik-table">
        <thead>
                <tr><th><?php _e('Date', 'wp-piwik'); ?></th><th class="n"><?php _e('Visits', 'wp-piwik'); ?></th><th class="n"><?php _e('Unique', 'wp-piwik'); ?></th><th class="n"><?php _e('Bounced', 'wp-piwik'); ?></tr>
        </thead>
        <tbody>
<?php
$aryTmp = array_reverse($aryConf['data']['Visitors']);
foreach ($aryTmp as $strDate => $intValue)
        echo '<tr><td>'.$strDate.'</td><td class="n">'.$intValue.'</td><td class="n">'.$aryConf['data']['Unique'][$strDate].'</td><td class="n">'.$aryConf['data']['Bounced'][$strDate].'</td></tr>';
unset($aryTmp);
?>
        </tbody>
</table>
</div>
<?php include ('footer.php');
