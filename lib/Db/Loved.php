<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A user's Last.fm "loved" track. Keyed per track identity (not per play):
 * `contentKey` is the sha1 of the normalised artist+track, making
 * (userId, contentKey) a stable idempotency key. `lovedAt` and `createdAt`
 * are Unix seconds.
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getArtist()
 * @method void setArtist(string $artist)
 * @method string getTrack()
 * @method void setTrack(string $track)
 * @method string getContentKey()
 * @method void setContentKey(string $contentKey)
 * @method string|null getRecordingMbid()
 * @method void setRecordingMbid(?string $recordingMbid)
 * @method int getLovedAt()
 * @method void setLovedAt(int $lovedAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class Loved extends Entity implements \JsonSerializable
{
    protected string $userId = '';
    protected string $artist = '';
    protected string $track = '';
    protected string $contentKey = '';
    protected ?string $recordingMbid = null;
    protected int $lovedAt = 0;
    protected int $createdAt = 0;

    public function __construct()
    {
        $this->addType('lovedAt', 'integer');
        $this->addType('createdAt', 'integer');
    }

    public function jsonSerialize(): array
    {
        return [
            'id'            => $this->id,
            'artist'        => $this->artist,
            'track'         => $this->track,
            'recordingMbid' => $this->recordingMbid,
            'lovedAt'       => $this->lovedAt,
        ];
    }
}
