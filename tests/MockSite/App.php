<?php
namespace Divergence\Tests\MockSite;

use Faker\Provider\Lorem;
use Divergence\IO\Database\SQL as SQL;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Tests\MockSite\Models\Forum\Post;
use Divergence\Tests\MockSite\Models\Forum\Thread;

use Divergence\Tests\MockSite\Models\Forum\Category;

class App extends \Divergence\App
{
    public static function setUp()
    {
        ini_set('error_reporting', E_ALL ^ E_NOTICE ^ E_WARNING); // or error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');

        if (static::isDatabaseTestingEnabled()) {
            static::clean();

            $Tags = [
                ['Tag'=>'Linux','Slug' => 'linux'],
                ['Tag'=>'OSX','Slug' => 'osx'],
                ['Tag'=>'PHPUnit','Slug' => 'phpunit'],
                ['Tag'=>'Unit Testing','Slug' => 'unit_testing'],
                ['Tag'=>'Refactoring','Slug' => 'refactoring'],
                ['Tag'=>'Encryption','Slug' => 'encryption'],
                ['Tag'=>'Canaries','Slug'=> 'canaries'],
            ];
            
            foreach ($Tags as &$Tag) {
                $Tag = Tag::create($Tag);
                $Tag->save();
            }

            fwrite(STDOUT, 'Summoning Canaries'."\n");

            $Canaries = [];
            while (count($Canaries) < 10) {
                $Canary = Canary::create(Canary::avis());
                
                $Canary->save();
                array_push($Canaries, $Canary);
            }

            $data = Canary::avis();
            $data['Name'] = 'Versioned';
            $data['Handle'] = 'Versioned';
            $Canary = Canary::create($data, true);
            $Canary->Name = 'Version2';
            $Canary->Handle = 'Version2';
            $Canary->save();


            // forum data
            $Categories = [
                'General' => [
                    'Hey guys',
                    'found this stuff',
                    'meme dump',
                    'sticky! check this first!!!',
                ],
                'Entertainment' => [
                    'Top movies this year',
                    'leaked album',
                    'Checkout this stream',
                    'Ratings info',
                ],
                'Technology' => [
                    'New prices drop for next gen phones! Still no aux plug',
                    'Bluetooth headphones compared',
                    'ipv6 adoption rate picking up',
                ],
                'Support' => [
                    'Seeing a design bug in my browser',
                    'help!',
                    'can i haz human???',
                ],
            ];
            foreach ($Categories as $Category=>$Threads) {
                $Category = Category::create([
                    'Name' => $Category,
                ], true);

                foreach ($Threads as $Thread) {
                    $Thread = Thread::create([
                        'Title' => $Thread,
                        'CategoryID' => $Category->ID,
                    ], true);

                    $x = rand(0, 10);
                    $i = 0;
                    while ($i<$x) {
                        Post::create([
                            'Content' => Lorem::text(rand(50, 400)),
                            'ThreadID' => $Thread->ID,
                        ], true);
                        $i++;
                    }
                }
            }
        }
    }
    public static function isDatabaseTestingEnabled()
    {
        try {
            return is_a(DB::getConnection(), \PDO::class);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function clean()
    {
        $tables = DB::allRecords('Show tables;');
        foreach ($tables as $data) {
            foreach ($data as $table) {
                DB::nonQuery("DROP TABLE `{$table}`");
            }
        }
    }

    public static function init($Path)
    {
        return parent::init($Path);
    }
}
