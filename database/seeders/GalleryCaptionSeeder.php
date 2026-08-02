<?php

namespace Database\Seeders;

use App\Models\GalleryPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Titles, captions, alt text and ordering for the imported photographs.
 *
 * The import command can only derive a filename; what a picture *shows* has to
 * be written by someone who looked at it. Captions here describe what is
 * visibly in each frame and nothing beyond it — no invented clients, projects
 * or dates. Everything is editable in the admin afterwards.
 *
 * Safe to re-run: matched on path, and it never overwrites a caption that has
 * since been edited by hand.
 */
class GalleryCaptionSeeder extends Seeder
{
    /**
     * category, title, caption, alt, order, published
     *
     * Several frames are near-identical alternates from the same moment. The
     * strongest of each set stays published and the rest are unpublished rather
     * than deleted — a gallery that shows the same shot three times reads as
     * unedited, and one toggle in the admin brings any of them back.
     */
    private const PHOTOS = [
        'gallery/proffesional-photo-mubaraka-muhindo.jpg' => [
            'Portrait', 'Muhindo Mubaraka',
            'Manager, Information Systems — software engineer and programming teacher.',
            'Muhindo Mubaraka, arms folded, wearing a white Eight Tech Consults shirt', 1, true,
        ],
        'gallery/4-img-1311.jpg' => [
            'Workspace', 'Deep work',
            'Most of a system gets built in hours that look exactly like this one.',
            'Working at a desk with a monitor of code, a laptop and a microphone', 2, true,
        ],
        'gallery/9-img-0601.jpg' => [
            'Training', 'Handover in progress',
            'A system is delivered when the team running it can keep it alive without me.',
            'Leaning over a colleague’s desk, walking them through software on screen', 3, true,
        ],
        'gallery/18-pxl-20260623-151852988.jpg' => [
            'Delivery', 'Working session',
            'Requirements get agreed across a table far more often than in a document.',
            'Two people at an outdoor table with a laptop between them, in discussion', 4, true,
        ],
        'gallery/1.jpg' => [
            'Workspace', 'Two screens, one problem',
            'Editor on one side, what it produces on the other.',
            'At a desk with a large monitor showing code and a laptop alongside', 5, true,
        ],
        'gallery/16-img-2108.jpg' => [
            'Delivery', 'The office travels',
            'Field systems get tested in the field, not in a meeting room.',
            'Sitting on the back of a pickup truck outdoors, working on a laptop', 6, true,
        ],
        'gallery/10-img-0583.jpg' => [
            'Delivery', 'Client session',
            'Scoping work with the people who will have to live with the result.',
            'Seated at a desk in a suit beside a monitor, mid-conversation', 7, true,
        ],
        'gallery/12-fb-img-1618669635184.jpg' => [
            'Study', 'Graduation',
            'The paperwork that says the studying happened.',
            'Wearing a green academic gown and cap on graduation day', 8, true,
        ],
        'gallery/13-img-20180318-232440.jpg' => [
            'Study', 'Late thesis nights',
            'Islamic University of Technology — the long stretch before a submission.',
            'Working late on a laptop, an IUT banner on the wall behind', 9, true,
        ],
        'gallery/15-20170703-151005.jpg' => [
            'Study', 'On campus',
            'Coursework, notes and a desk that was mostly paper at the time.',
            'Seated at a desk in an IUT shirt with papers spread out', 10, true,
        ],
        'gallery/3-img-1673.jpg' => [
            'Team', 'The team',
            'Work gets shipped by people, and people mark the milestones.',
            'Three colleagues around a cake in an office, celebrating together', 11, true,
        ],
        'gallery/7-img-0968.jpg' => [
            'Workspace', 'Where it happens',
            'Laptop, second screen, microphone — the recording and the building share a desk.',
            'A desk holding a closed laptop, an external monitor and a studio microphone', 12, true,
        ],
        'gallery/2-20231011-131849.jpg' => [
            'Teaching', 'Still reading',
            'Teaching a thing is the fastest way to find out how well you know it.',
            'Holding a book to the camera with headphones around the neck', 13, true,
        ],
        'gallery/20250210-142022.jpg' => [
            'Teaching', 'Between recordings',
            'Two hundred tutorials in, the headphones live around my neck.',
            'Smiling at the camera wearing headphones and a branded work shirt', 14, true,
        ],
        'gallery/11-img-20211029-122526.jpg' => [
            'Workspace', 'At the desk',
            'A working day, somewhere in the middle of it.',
            'Close portrait at a desk with a laptop open in the background', 15, true,
        ],
        'gallery/12-img-6544.jpg' => [
            'Study', 'Graduating together',
            'Nobody finishes a degree on their own.',
            'Two graduates in academic gowns standing together outdoors', 16, true,
        ],

        // Alternates from the same moments — kept, not published.
        'gallery/5-img-1307.jpg' => ['Workspace', 'Deep work (alternate)', null, 'Working at a desk with a monitor of code', 90, false],
        'gallery/6-img-1302.jpg' => ['Workspace', 'Deep work (alternate)', null, 'At a desk holding a phone, monitor of code behind', 91, false],
        'gallery/8-img-0967.jpg' => ['Workspace', 'Desk (alternate)', null, 'A desk with a laptop, monitor and microphone', 92, false],
        'gallery/17-img-2098.jpg' => ['Delivery', 'Pickup (alternate)', null, 'Sitting on a pickup truck with a laptop', 93, false],
        'gallery/14-img-20180318-2324372.jpg' => ['Study', 'Thesis nights (alternate)', null, 'Working late on a laptop', 94, false],
        'gallery/18-pxl-20260623-151854887.jpg' => ['Delivery', 'Working session (alternate)', null, 'Two people at an outdoor table with a laptop', 95, false],
        'gallery/19-pxl-20260623-151850708.jpg' => ['Delivery', 'Working session (alternate)', null, 'Two people at an outdoor table with a laptop', 96, false],
        'gallery/img-20220107-120908.jpg' => ['Portrait', 'Off duty', null, 'Smiling at the camera in sunglasses, holding a laptop', 97, false],
    ];

    public function run(): void
    {
        $written = 0;

        foreach (self::PHOTOS as $path => [$category, $title, $caption, $alt, $order, $published]) {
            $photo = GalleryPhoto::where('path', $path)->first();

            if (! $photo) {
                continue;
            }

            /* "Edited by hand" is taken to mean the title no longer matches
               what the importer derived from the filename. That is a simple,
               checkable rule, and it means re-running this seeder after someone
               has rewritten a caption leaves their words alone. */
            $untouched = $photo->title === Str::headline(pathinfo($path, PATHINFO_FILENAME));

            if ($untouched) {
                $photo->title = $title;
                $photo->caption = $caption;
            }

            $photo->category = $category;
            $photo->alt = $photo->alt ?: $alt;
            $photo->sort_order = $order;
            $photo->is_published = $published;
            /* Evaluated before the && rather than inside it. Short-circuiting
               over a constant array means the comparison is unreachable on the
               rows where $published is literally false, which makes $order's
               type impossible there. */
            $withinFeaturedRange = $order <= 6;
            $photo->is_featured = $published && $withinFeaturedRange;
            $photo->save();
            $written++;
        }

        $this->command->info("GalleryCaptionSeeder: captioned {$written} photographs.");
    }
}
