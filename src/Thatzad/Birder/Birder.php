<?php namespace Thatzad\Birder;

use URL;
use Config;
use Thujohn\Twitter\Twitter;
use Illuminate\Support\Collection;
use DotZecker\Larafeed\Larafeed as Feed;

class Birder {

    /**
     * Type to look for
     */
    protected $type = 'user';

    /**
     * Value to look for
     */
    protected $value;

    /**
     * Found tweets
     */
    protected $tweets;


    public function __construct(Twitter $twitter)
    {
        $this->twitter = $twitter;
        $this->config  = Config::get('birder.general');
    }


    /**
     * Fill the user data
     */
    public function user($user)
    {
        $this->type  = 'user';
        $this->value = $user;

        return $this;
    }

    /**
     * Fill the hashtag data
     */
    public function hashtag($hashtag)
    {
        // Remove the #
        if (strstr($hashtag, '#')) $hashtag = substr($hashtag, 1, strlen($hashtag));

        $this->type  = 'hashtag';
        $this->value = $hashtag;

        return $this;
    }

    /**
     * Set the tweets
     * @param array $tweets
     */
    protected function setTweets($tweets)
    {
        $config = $this->config;

        $filteredTweets = array();

        foreach ($tweets as $tweet) {
            if ($tweet->retweet_count > $config['min_rts'] or $tweet->favorite_count > $config['min_favs']) {
                $filteredTweets[] = $tweet;
            }
        }

        $tweets = new Collection($filteredTweets);
        $this->tweets = $tweets->reverse();
    }

    /**
     * Get the tweets by user
     * @return void
     */
    protected function generateTweetsByUser()
    {
        $this->setTweets($this->twitter->getUserTimeline(array(
            'screen_name'     => $this->value,
            'inclide_rts'     => false,
            'exclude_replies' => true,
            'trim_user'       => false
        )));
    }

    /**
     * Get the tweets by hashtag
     * @return void
     */
    protected function generateTweetsByHashtag()
    {
        $this->setTweets($this->twitter->getSearch(array(
            'q'           => '#'.$this->value,
            'result_type' => 'recent'
        ))->statuses);
    }

    /**
     * Make the feed
     * @return Response
     */
    public function makeFeed()
    {
        $this->{'generateTweetsBy'.ucfirst($this->type)}();

        $feed = new Feed('atom', array(
            'title' => "Generated tweets looking for {$this->type} {$this->value}",
            'link'  => URL::to('/'),
            'description' => "Timeline generated by Birder"
        ));

        $feed->addAuthor('Birder');

        $this->tweets->each(function($tweet) use ($feed)
        {
            $feed->addEntry(array(
                'title'   => $tweet->id_str,
                'link'    => "https://twitter.com/{$tweet->user->screen_name}/status/{$tweet->id_str}",
                'author'  => '@'.$tweet->user->screen_name,
                'pubDate' => $tweet->created_at,
                'content' => $tweet->text
            ));
        });

        return $feed->render();
    }

    /**
     * Return the tweets collection
     * @return Collection
     */
    public function collection()
    {
        $this->{'generateTweetsBy'.ucfirst($this->type)}();

        return $this->tweets;
    }

    /**
     * Set the min rts
     */
    public function minRetweets($num)
    {
        $this->config['min_rts'] = $num;

        return $this;
    }

    /**
     * Set the min favs
     */
    public function minFavorites($num)
    {
        $this->config['min_favs'] = $num;

        return $this;
    }

}