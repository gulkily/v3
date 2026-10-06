<?php
$agentResponseModes = is_array($agentResponseModes ?? null) ? $agentResponseModes : [];
$agentResponseModesJson = json_encode(
    $agentResponseModes,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
);
?>
<?php if ($agentResponseModes !== [] && is_string($agentResponseModesJson)): ?>
<script type="application/json" data-agent-response-mode-catalog><?= $agentResponseModesJson ?></script>
<?php endif; ?>
