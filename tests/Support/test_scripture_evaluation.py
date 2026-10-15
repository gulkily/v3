import json
from pathlib import Path
import tempfile
import unittest
from scripture_evaluation import Budget
from scripture_live_evaluation import ProviderProxy, resume_results
from unittest.mock import patch


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


class ProviderProxyTest(unittest.TestCase):
    def test_unapproved_payload_never_reserves_or_calls(self):
        with tempfile.TemporaryDirectory() as directory:
            proxy = ProviderProxy(Path(directory), 'secret')
            with patch('scripture_live_evaluation.urlopen') as upstream:
                with self.assertRaises(ValueError):
                    proxy.call({'model': 'other', 'max_completion_tokens': 8192})
                upstream.assert_not_called()
            self.assertEqual(proxy.budget.entries, [])

    def test_success_settles_uncached_upper_bound_and_retains_usage(self):
        with tempfile.TemporaryDirectory() as directory:
            proxy = ProviderProxy(Path(directory), 'secret')
            proxy.sample = 'anonymous-sample'
            response = {'usage': {'prompt_tokens': 100, 'completion_tokens': 200},
                        'choices': [{'message': {'content': 'Answer.'}}]}
            from unittest.mock import MagicMock
            upstream_response = MagicMock()
            upstream_response.__enter__.return_value.read.return_value = json.dumps(response).encode()
            with patch('scripture_live_evaluation.urlopen', return_value=upstream_response):
                code, _ = proxy.call({'model': 'gpt-5-nano', 'messages': [], 'max_completion_tokens': 8192})
            self.assertEqual(code, 200)
            self.assertTrue(proxy.budget.entries[0]['settled'])
            self.assertAlmostEqual(proxy.budget.entries[0]['cost'], .000085)
            exchange = (Path(directory) / 'exchanges.jsonl').read_text()
            self.assertNotIn('secret', exchange)
            self.assertEqual(json.loads(exchange)['sample'], 'anonymous-sample')

    def test_uncertain_charge_survives_failure_and_budget_prevents_dispatch(self):
        with tempfile.TemporaryDirectory() as directory:
            proxy = ProviderProxy(Path(directory), 'secret')
            proxy.budget = Budget(Path(directory) / 'ledger.json', generation_limit=1)
            payload = {'model': 'gpt-5-nano', 'messages': [], 'max_completion_tokens': 8192}
            with patch('scripture_live_evaluation.urlopen', side_effect=TimeoutError) as upstream:
                with self.assertRaises(TimeoutError):
                    proxy.call(payload)
                with self.assertRaises(RuntimeError):
                    proxy.call(payload)
                self.assertEqual(upstream.call_count, 1)
            self.assertEqual(proxy.budget.entries[0]['settled'], False)
            self.assertGreater(proxy.budget.entries[0]['cost'], 8192 * .4 / 1000000)


class ResumeTest(unittest.TestCase):
    def test_interruption_keeps_reservation_and_is_not_reissued(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'frozen.json').write_text('{}')
            (root / 'exchanges.jsonl').write_text('')
            row = {'sample': 'initial-42', 'outcome': 'interrupted', 'reservation_index': 0}
            (root / 'results.jsonl').write_text(json.dumps(row) + '\n')
            entries = [{'kind': 'generation', 'settled': False, 'cost': .004}]
            self.assertEqual(resume_results(root, {}, entries), [row])
            self.assertFalse(entries[0]['settled'])
            (root / 'results.jsonl').write_text('')
            with self.assertRaises(ValueError):
                resume_results(root, {}, entries)

    def test_prior_precall_rejection_does_not_override_completed_sample(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'frozen.json').write_text('{}')
            (root / 'exchanges.jsonl').write_text(json.dumps({'sample': 'initial-22'}) + '\n')
            failed = {'sample': 'initial-22', 'outcome': 'harness_error', 'error': 'body must be ASCII only'}
            complete = {'sample': 'initial-22', 'outcome': 'complete'}
            (root / 'results.jsonl').write_text(json.dumps(failed) + '\n' + json.dumps(complete) + '\n')
            self.assertEqual(resume_results(root, {}, []), [complete])
            (root / 'results.jsonl').write_text(json.dumps(failed) + '\n')
            with self.assertRaises(ValueError):
                resume_results(root, {}, [])


if __name__ == '__main__':
    unittest.main()
