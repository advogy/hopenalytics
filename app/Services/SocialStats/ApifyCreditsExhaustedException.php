<?php

namespace App\Services\SocialStats;

use RuntimeException;

/**
 * Thrown by ApifyClient specifically when Apify rejects a run for billing/usage reasons
 * (out of credits, monthly usage limit reached) rather than a normal actor/network failure —
 * lets FetchSingleChurchData tell "this account needs retrying later" apart from "this whole
 * integration is out of budget right now, stop hammering it and fall back to manual entry".
 */
class ApifyCreditsExhaustedException extends RuntimeException {}
