<?php

namespace Modules\Proposals\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Core\Numbering\NextNumber;
use Modules\Proposals\Proposal;
use Modules\Proposals\ProposalTemplate;

class ProposalTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales');
    }

    public function index()
    {
        return view('proposals.templates', ['templates' => ProposalTemplate::orderBy('name')->get()]);
    }

    /** Save any proposal's full content as a reusable starter. */
    public function saveAs(Request $r, Proposal $proposal)
    {
        $data = $r->validate(['name' => 'required|string|max:120']);
        $clean = fn ($v) => is_string($v) ? mb_convert_encoding($v, 'UTF-8', 'UTF-8') : $v;
        ProposalTemplate::create([
            'name' => $data['name'], 'gst_rate' => $proposal->gst_rate, 'discount' => $proposal->discount,
            'place_of_supply' => $proposal->place_of_supply, 'notes' => $proposal->notes,
            'scope' => $proposal->scope, 'results' => $proposal->results, 'terms' => $proposal->terms,
            'items' => $proposal->items->map(fn ($it) => ['description' => $clean($it->description), 'hsn_code' => $clean($it->hsn_code), 'unit' => $clean($it->unit), 'tax_rate' => $it->tax_rate, 'billing' => $it->billing ?? 'once', 'qty' => $it->qty, 'rate' => $it->rate])->values()->all(),
            'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Saved as template “'.$data['name'].'”.');
    }

    /** Start a draft client + content prefilled from a template. */
    public function use(Request $r, ProposalTemplate $template, \Modules\Settings\SettingStore $s)
    {
        $data = $r->validate(['client_id' => 'required|exists:clients,id']);
        $prop = Proposal::create([
            'number' => NextNumber::take($s->get('proposal.prefix', 'PROP-'), 'proposals'),
            'client_id' => $data['client_id'], 'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays((int) $s->get('proposal.valid_days', 15))->toDateString(),
            'gst_rate' => $template->gst_rate, 'discount' => $template->discount,
            'place_of_supply' => $template->place_of_supply, 'notes' => $template->notes,
            'scope' => $template->scope, 'results' => $template->results, 'terms' => $template->terms,
            'sign_token' => Str::random(32), 'created_by' => auth()->id(),
        ]);
        foreach ($template->items ?? [] as $it) {
            $prop->items()->create([
                'description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null,
                'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null,
                'billing' => $it['billing'] ?? 'once', 'qty' => $it['qty'], 'rate' => $it['rate'],
                'amount' => round($it['qty'] * $it['rate'], 2),
            ]);
        }
        $prop->recalc();

        return redirect()->route('proposals.edit', $prop)->with('ok', 'Draft started from “'.$template->name.'” — review and send.');
    }

    public function destroy(ProposalTemplate $template)
    {
        $template->delete();

        return back()->with('ok', 'Template deleted.');
    }
}
