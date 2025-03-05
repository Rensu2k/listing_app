<?php

namespace App\Http\Controllers;

use App\Http\Middleware\NotSuspended;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ListingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(
                ['auth', 'verified', NotSuspended::class],
                except: ['index', 'show']
            )
        ];
    }


    public function index(Request $request)
    {
        $listings = Listing::whereHas('user', function (Builder $query) {
            $query->where('role', '!=', 'suspended');
        })
            ->with('user')
            ->where('approved', true)
            ->filter(request(['search', 'user_id', 'tag']))
            ->latest()
            ->paginate(6)
            ->withQueryString();

        return Inertia::render('Home', [
            'listings' => $listings,
            'searchTerm' => $request->search,
        ]);
    }


    public function create()
    {
        Gate::authorize('create', Listing::class);

        return Inertia::render('Listing/Create');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Listing::class);

        $fields = $request->validate([
            'title' => ['required', 'max:255'],
            'desc' => ['required'],
            'tags' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'link' => ['nullable', 'url'],
            'image' => ['nullable', 'file', 'max:3072', 'mimes:jpeg,jpg,png,webp']
        ]);

        if ($request->hasFile('image')) {
            $fields['image'] = Storage::disk('public')->put('images/listing', $request->image);
        }

        $fields['tags'] = implode(',', array_unique(array_filter(array_map('trim', explode(',',  $request->tags)))));

        
        $request->user()->listings()->create($fields);

        return redirect()->route('dashboard')->with('status', 'Listing created successfully.');
    }


    public function show(Listing $listing)
    {
        Gate::authorize('view', $listing);

        return Inertia::render('Listing/Show', [
            'listing' => $listing,
            'user' => $listing->user->only(['name', 'id']),
            // 'canModify' => Auth::user() ? Auth::user()('modify', $listing) : false
            'canModify' => Gate::allows('modify', $listing),

        ]);
    }


    public function edit(Listing $listing)
    {
        Gate::authorize('modify', $listing);

        return Inertia::render('Listing/Edit', [
            'listing' => $listing
        ]);
    }


    public function update(Request $request, Listing $listing)
    {
        Gate::authorize('modify', $listing);

        $fields = $request->validate([
            'title' => ['required', 'max:255'],
            'desc' => ['required'],
            'tags' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'link' => ['nullable', 'url'],
            'image' => ['nullable', 'file', 'max:3072', 'mimes:jpeg,jpg,png,webp']
        ]);

        if ($request->hasFile('image')) {
            if ($listing->image) {
                Storage::disk('public')->delete($listing->image);
            }
            $fields['image'] = Storage::disk('public')->put('images/listing', $request->image);
        } else {
            $fields['image'] = $listing->image;
        }

        $fields['tags'] = implode(',', array_unique(array_filter(array_map('trim', explode(',',  $request->tags)))));

        $listing->update([...$fields, 'approved' => false]);

        return redirect()->route('dashboard')->with('status', 'Listing updated successfully.');
    }


    public function destroy(Listing $listing)
    {
        Gate::authorize('modify', $listing);

        if ($listing->image) {
            Storage::disk('public')->delete($listing->image);
        }

        $listing->delete();

        return redirect()->route('dashboard')->with('status', 'Listing deleted successfully.');
    }
}
