import json
from pathlib import Path
import tempfile
import unittest
from scripture_evaluation import Budget


class BudgetTest(unittest.TestCase):
    def test_failed_calls_and_restarts_cannot_reset_budget(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'ledger.json'
            budget = Budget(path, dollars=1, generation_limit=2)
            first = budget.reserve('generation', .6)
            with self.assertRaises(RuntimeError):
                budget.reserve('moderation', .5)
            budget.settle(first, .2)
            budget.reserve('generation', .6)  # Simulated failed/unknown call.
            resumed = Budget(path, dollars=1, generation_limit=2)
            with self.assertRaises(RuntimeError):
                resumed.reserve('generation', .01)
            with self.assertRaises(RuntimeError):
                resumed.reserve('moderation', .3)
            self.assertEqual(len(json.loads(path.read_text())), 2)


if __name__ == '__main__':
    unittest.main()
