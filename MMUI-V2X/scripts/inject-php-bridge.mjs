import fs from 'node:fs/promises';
import path from 'node:path';

const distIndex = path.resolve(process.cwd(), process.env.MMUI_ECS_DIST_DIR || process.env.MMUI_OUT_DIR || '../qzsystem/public/src/static/mmui', 'index.html');
const qzViewIndex = path.resolve(process.cwd(), process.env.MMUI_ECS_BUNDLE_PATH || '../qzsystem/view/control/ecs/mmui_bundle.html');

const phpPrelude = `<?php
$mmuiVuePayload = $mmuiVuePayload ?? [];
$mmuiVueRequestKey = $mmuiVueRequestKey ?? 'mmui_vue_json';
$mmuiVueUseMock = empty($mmuiVuePayload);
$mmuiVueRequestUri = $_SERVER['REQUEST_URI'] ?? '';
$mmuiVueSeparator = strpos($mmuiVueRequestUri, '?') === false ? '?' : '&';
$mmuiVueJsonUrl = $mmuiVueJsonUrl ?? ($mmuiVueRequestUri . $mmuiVueSeparator . $mmuiVueRequestKey . '=1');

if (isset($_GET[$mmuiVueRequestKey]) && $_GET[$mmuiVueRequestKey] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($mmuiVuePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>
`;

const replacements = [
  [
    '__MMUI_VUE_EMBEDDED_JSON__',
    '<?php echo json_encode($mmuiVuePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>',
  ],
  [
    "'__MMUI_VUE_JSON_URL__'",
    '<?php echo json_encode($mmuiVueJsonUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>',
  ],
  [
    "'__MMUI_VUE_REQUEST_KEY__'",
    '<?php echo json_encode($mmuiVueRequestKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>',
  ],
  ["'__MMUI_VUE_ADAPTER__'", "'qz-ecs'"],
  ['useMock: true', 'useMock: <?php echo $mmuiVueUseMock ? "true" : "false"; ?>'],
];

const analyticsInclude = `    <?php include app()->getRootPath() . 'view/control/ecs/mmui_analytics.html'; ?>\n`;

const source = await fs.readFile(distIndex, 'utf8');
let output = phpPrelude + source;

for (const [needle, replacement] of replacements) {
  output = output.replace(needle, replacement);
}

if (!output.includes('mmui_analytics.html')) {
  const withAnalytics = output.replace(/(\s*<script type="module" crossorigin src=)/, `\n${analyticsInclude}$1`);
  output = withAnalytics.includes('mmui_analytics.html')
    ? withAnalytics
    : output.replace('</head>', `${analyticsInclude}  </head>`);
}

await fs.mkdir(path.dirname(qzViewIndex), { recursive: true });
await fs.writeFile(qzViewIndex, output, 'utf8');
