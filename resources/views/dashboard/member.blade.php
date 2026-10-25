@extends('web::layouts.grids.12')

@section('title', 'My Activities')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">My Activities</h4>
                <div class="card-tools pull-right">
                    @if($isCurrentMonth)
                        <span class="badge badge-info">Live - current month in progress</span>
                    @else
                        <span class="badge badge-primary">Finalized</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if(!$hasLinkedCharacters)
                    <div class="alert alert-warning mb-0">
                        No characters are linked to your SeAT account yet, so there's nothing to show.
                    </div>
                @else
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label>Month:</label>
                            <div class="btn-group flex-wrap" role="group">
                                @foreach($availableMonths as $availableMonth)
                                    <a href="?month={{ $availableMonth->format('Y-m') }}"
                                       class="btn btn-sm @if($availableMonth->equalTo($month)) btn-primary @else btn-default @endif">
                                        {{ $availableMonth->format('M Y') }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <h5>Tax Contributions</h5>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title text-muted">PvE Bounty &amp; Tax</h6>
                                    <h3 class="mb-0">{{ number_format($stats->pve_bounty_amount) }} ISK</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title text-muted">Industry Tax</h6>
                                    <h3 class="mb-0">{{ number_format($stats->industry_tax_amount) }} ISK</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title text-muted">Mining Tax</h6>
                                    <h3 class="mb-0">{{ number_format($stats->mining_tax_amount) }} ISK</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5>PvP</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title text-muted">Kills</h6>
                                    <h3 class="mb-0">{{ $stats->pvp_kills }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title text-muted">ISK Destroyed</h6>
                                    <h3 class="mb-0">{{ number_format($stats->isk_destroyed) }} ISK</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title text-muted">Fleets</h6>
                                    <h3 class="mb-0">{{ $stats->fleet_count }}</h3>
                                    <div class="small text-muted">{{ $stats->fleet_instances }} instances</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title text-muted">Losses</h6>
                                    <h3 class="mb-0">{{ $stats->pvp_losses }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="card-title text-muted">ISK Lost</h6>
                                    <h3 class="mb-0">{{ number_format($stats->isk_lost) }} ISK</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
