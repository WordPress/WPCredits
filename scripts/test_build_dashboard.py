"""Tests for the dashboard build. Run from the repository root:

    python3 -m unittest discover -s scripts -p 'test_*.py' -v

The build imports requests at the top for its Airtable and WordPress.org calls; none of
the code under test makes a request, so a stub stands in for it when it is not installed.
"""

import sys
import types
import unittest
from pathlib import Path

SCRIPTS = Path(__file__).parent
sys.modules.setdefault("requests", types.ModuleType("requests"))
sys.path.insert(0, str(SCRIPTS))

import build_dashboard  # noqa: E402

NEW_CITIES = {
    ("managua", "Nicaragua"): "Managua",
    ("serang", "Indonesia"): "Serang",
    ("purwokerto", "Indonesia"): "Purwokerto",
    ("trento", "Italy"): "Trento",
    ("roma", "Italy"): "Rome",
    ("ferrara", "Italy"): "Ferrara",
    ("bari", "Italy"): "Bari",
    ("valencia", "Spain"): "Valencia",
}


class PartnerMapTest(unittest.TestCase):
    def test_new_cities_have_coordinates(self):
        for key, display in NEW_CITIES.items():
            lat, lng, shown = build_dashboard.CITY_COORDS[key]
            self.assertEqual(shown, display)
            self.assertTrue(-90 <= lat <= 90 and -180 <= lng <= 180)

    def test_uppercase_airtable_city_gets_a_pin(self):
        # Airtable stores "PURWOKERTO"; the lookup lowercases it.
        f = build_dashboard.FIELDS["institutions"]
        rec = {"id": "rec1", "fields": {f["name"]: "Universitas Muhammadiyah Purwokerto",
               f["current_stage"]: {"name": "Confirmed"}, f["country"]: ["recID"],
               f["city"]: "PURWOKERTO", f["website"]: "ump.ac.id"}}
        pins = build_dashboard.build_inst_markers([rec], {"recID": {"name": "Indonesia"}})
        self.assertEqual(pins[0]["city"], "Purwokerto")
        self.assertEqual(pins[0]["institutions"][0]["url"], "https://ump.ac.id")


if __name__ == "__main__":
    unittest.main()
