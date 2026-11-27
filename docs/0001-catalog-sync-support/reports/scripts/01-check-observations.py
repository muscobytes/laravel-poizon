"""Offline evidence checks; run in Docker with the repository mounted read-only."""

import json
from pathlib import Path

source = Path(__file__).resolve().parents[1] / "01-time-window-observations.jsonl"
rows = [json.loads(line) for line in source.read_text().splitlines()]
assert len(rows) == 52
assert all(r["http"] == 200 and r["code"] == 200 and r["attempts"] == 1 for r in rows)
excluded = {"upper_-1", "lower_1", "reversed", "seconds_instead_of_ms", "start_only_after", "end_only_before"}
for operation, field in [("querySpuList", "dwSpuId"), ("querySkuList", "dwSkuId")]:
    subset = [r for r in rows if r["operation"] == operation]
    for r in subset:
        if r["case"].startswith("partition_"):
            continue
        assert len(r["records"]) == (0 if r["case"] in excluded else 1), r["case"]
        assert r["pagination"]["total"] == len(r["records"])
        for record in r["records"]:
            assert record[field] == r["parameters"][field][0]
            assert record["modifyTime"] == r["control_modify_time"]
            assert record["modifyTime"] >= r["parameters"].get("modifyStartTime", 0)
            assert record["modifyTime"] <= r["parameters"].get("modifyEndTime", 2**63 - 1)
    parts = {r["case"]: r for r in subset if r["case"].startswith("partition_")}
    sets = {k: {str(v[field]) for v in r["records"]} for k, r in parts.items()}
    assert len(sets["partition_whole"]) == 3
    assert len(sets["partition_left"]) == len(sets["partition_right"]) == 2
    assert sets["partition_whole"] == sets["partition_left"] | sets["partition_right"]
    assert len(sets["partition_left"] & sets["partition_right"]) == 1
    for r in parts.values():
        assert r["pagination"]["total"] == len(r["records"])
        assert len(r["records"]) < r["parameters"]["pageSize"]
        assert all(r["parameters"]["modifyStartTime"] <= v["modifyTime"] <= r["parameters"]["modifyEndTime"] for v in r["records"])
print("OK: 52 sanitized live observations; both inclusive bounds; singleton windows; stable controls; exact 3-ID partition unions with one overlap each.")
