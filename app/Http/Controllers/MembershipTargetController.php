<?php

namespace App\Http\Controllers;

use App\Support\MembershipTargetList;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Tujuan → "Anggota & Target": recorded membership and its target, without statistics. */
class MembershipTargetController extends Controller
{
    private const PER_PAGE = 100;

    public function index(Request $request)
    {
        $list = MembershipTargetList::build($request, $request->user());

        $page = LengthAwarePaginator::resolveCurrentPage();
        $list['paginator'] = (new LengthAwarePaginator(
            $list['rows']->forPage($page, self::PER_PAGE)->values(),
            $list['rows']->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));

        return view('goals.members', $list);
    }
}
