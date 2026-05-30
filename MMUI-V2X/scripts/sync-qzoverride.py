from __future__ import annotations

import argparse
import shutil
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[2]
OVERRIDE_ROOT = REPO_ROOT / "MMUI-V2X" / "QzOverride"

SYNC_PATHS = [
    "qzsystem/.env",
    "qzsystem/.gitignore",
    "qzsystem/app/common.php",
    "qzsystem/app/admin/controller/Index.php",
    "qzsystem/app/admin/controller/Mmui.php",
    "qzsystem/app/common/service/Ecs.php",
    "qzsystem/app/common/service/ExecuteTask.php",
    "qzsystem/app/control/controller/Ecs.php",
    "qzsystem/app/install/data/data.sql",
    "qzsystem/config/web.php",
    "qzsystem/database/mmui-demo-data.sql",
    "qzsystem/database/mmui-mock-node.sql",
    "qzsystem/docs/mmui-qz-ecs-api.md",
    "qzsystem/docs/mmui-qz-mock-node.md",
    "qzsystem/public/install.lock",
    "qzsystem/view/admin/login/index.html",
    "qzsystem/view/admin/mmui/index.html",
    "qzsystem/view/control/ecs/mmui.html",
    "qzsystem/view/control/ecs/mmui_bundle.html",
    "qzsystem/view/control/ecs/mmui_dev.html",
]


def resolve_under(root: Path, relative: str) -> Path:
    candidate = (root / relative).resolve()
    root_resolved = root.resolve()
    if candidate != root_resolved and root_resolved not in candidate.parents:
        raise ValueError(f"path escapes root: {relative}")
    return candidate


def copy_one(relative: str, apply: bool) -> str:
    source = resolve_under(REPO_ROOT, relative)
    target = resolve_under(OVERRIDE_ROOT, relative)
    if not source.exists():
        return f"missing  {relative}"

    if apply:
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)
        return f"copied   {relative}"

    return f"would copy {relative}"


def main() -> int:
    parser = argparse.ArgumentParser(description="Sync qzsystem overrides into MMUI-V2X/QzOverride.")
    parser.add_argument("--apply", action="store_true", help="copy files instead of showing a dry run")
    args = parser.parse_args()

    for relative in SYNC_PATHS:
        print(copy_one(relative, args.apply))

    if not args.apply:
        print("dry run only; rerun with --apply to copy files")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
