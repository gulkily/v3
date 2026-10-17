import io
import json
from pathlib import Path
import tempfile
import unittest

from scripture_revision_evaluation import RevisionProxy, PROTOCOL, revision_stub


class RevisionBudgetTest(unittest.TestCase):
    def make_proxy(self, root, transport=revision_stub):
        (root / 'protocol.json').write_text(PROTOCOL.read_text())
        return RevisionProxy(root, 'never-log-this-key', transport)

    def payload(self, model):
        return {'model': model, 'messages': [{'content':'plain'}, {'content':'concern'}], 'max_completion_tokens':8192, 'reasoning_effort':'low'}

    def test_rates_share_durable_ledger_and_moderation_keeps_mini(self):
        with tempfile.TemporaryDirectory() as name:
            root = Path(name)
            proxy = self.make_proxy(root)
            proxy.sample = 'regression-1-1-C'
            proxy.call(self.payload('gpt-5.6-sol'))
            proxy.call(dict(self.payload('gpt-5-mini'), response_format={'type':'json_schema'}))
            resumed = self.make_proxy(root)
            resumed.sample = 'regression-1-1-B'
            resumed.call(self.payload('gpt-5-mini'))
            self.assertEqual(len(resumed.budget.entries), 3)
            self.assertAlmostEqual(resumed.budget.entries[0]['cost'], .0045)
            self.assertEqual(resumed.budget.dollars, 10)
            self.assertNotIn('never-log-this-key', (root/'exchanges.jsonl').read_text())
            with self.assertRaises(ValueError): resumed.call(self.payload('gpt-5.6-sol'))

    def test_model_rejection_halts_all_later_dispatch_without_retry(self):
        def wrong_model(request):
            body = json.loads(revision_stub(request).read())
            body['model'] = 'different-model'
            return io.BytesIO(json.dumps(body).encode())
        with tempfile.TemporaryDirectory() as name:
            root = Path(name)
            proxy = self.make_proxy(root, wrong_model)
            proxy.sample = 'regression-1-1-C'
            proxy.call(self.payload('gpt-5.6-sol'))
            self.assertTrue((root/'halt.json').exists())
            with self.assertRaises(ValueError): proxy.call(self.payload('gpt-5.6-sol'))
            self.assertEqual(len(proxy.budget.entries), 1)

    def test_input_and_count_ceiling_prevent_dispatch(self):
        with tempfile.TemporaryDirectory() as name:
            root = Path(name)
            proxy = self.make_proxy(root)
            proxy.sample = 'regression-1-1-C'
            payload = self.payload('gpt-5.6-sol')
            payload['messages'][1]['content'] = 'x' * 2100000
            with self.assertRaises(ValueError): proxy.call(payload)
            self.assertEqual(proxy.budget.entries, [])
            proxy.budget.limits['generation'] = 1
            proxy.call(self.payload('gpt-5.6-sol'))
            with self.assertRaises(RuntimeError): proxy.call(self.payload('gpt-5.6-sol'))


class RevisionGatesTest(unittest.TestCase):
    def test_missing_qualification_prevents_paid_dispatch(self):
        from scripture_revision_evaluation import run, save_gates
        with tempfile.TemporaryDirectory() as name:
            root = Path(name)
            (root/'protocol.json').write_text(PROTOCOL.read_text())
            (root/'results.jsonl').touch()
            (root/'ratings.json').write_text('{}')
            (root/'blind-key.json').write_text('{}')
            with self.assertRaises(ValueError): save_gates(root, 'regression')
            with self.assertRaises(FileNotFoundError): run(root, 'initial')
            self.assertFalse((root/'ledger.json').exists())


if __name__ == '__main__':
    unittest.main()
