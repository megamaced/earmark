<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A single recorded play. `listenedAt`, `resolvedAt` and `createdAt` are
 * Unix timestamps (seconds). `contentKey` / `dedupHash` are derived by
 * {@see \OCA\Earmark\Normalize}. `resolutionState` drives the asynchronous
 * MusicBrainz resolver job.
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getArtist()
 * @method void setArtist(string $artist)
 * @method string getTrack()
 * @method void setTrack(string $track)
 * @method string|null getAlbum()
 * @method void setAlbum(?string $album)
 * @method string getContentKey()
 * @method void setContentKey(string $contentKey)
 * @method string getDedupHash()
 * @method void setDedupHash(string $dedupHash)
 * @method string|null getArtistMbid()
 * @method void setArtistMbid(?string $artistMbid)
 * @method string|null getRecordingMbid()
 * @method void setRecordingMbid(?string $recordingMbid)
 * @method string|null getReleaseMbid()
 * @method void setReleaseMbid(?string $releaseMbid)
 * @method int getListenedAt()
 * @method void setListenedAt(int $listenedAt)
 * @method string getSource()
 * @method void setSource(string $source)
 * @method string getResolutionState()
 * @method void setResolutionState(string $resolutionState)
 * @method int|null getResolvedAt()
 * @method void setResolvedAt(?int $resolvedAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class Listen extends Entity implements \JsonSerializable
{
    /** Not yet attempted by the MusicBrainz resolver. */
    public const STATE_PENDING = 'pending';
    /** Resolved to at least one MBID. */
    public const STATE_RESOLVED = 'resolved';
    /** Resolver ran but MusicBrainz returned no confident match. */
    public const STATE_UNMATCHED = 'unmatched';

    public const STATES = [
        self::STATE_PENDING,
        self::STATE_RESOLVED,
        self::STATE_UNMATCHED,
    ];

    protected string $userId = '';
    protected string $artist = '';
    protected string $track = '';
    protected ?string $album = null;
    protected string $contentKey = '';
    protected string $dedupHash = '';
    protected ?string $artistMbid = null;
    protected ?string $recordingMbid = null;
    protected ?string $releaseMbid = null;
    protected int $listenedAt = 0;
    protected string $source = '';
    protected string $resolutionState = self::STATE_PENDING;
    protected ?int $resolvedAt = null;
    protected int $createdAt = 0;

    public function __construct()
    {
        $this->addType('listenedAt', 'integer');
        $this->addType('resolvedAt', 'integer');
        $this->addType('createdAt', 'integer');
    }

    public function jsonSerialize(): array
    {
        return [
            'id'              => $this->id,
            'artist'          => $this->artist,
            'track'           => $this->track,
            'album'           => $this->album,
            'artistMbid'      => $this->artistMbid,
            'recordingMbid'   => $this->recordingMbid,
            'releaseMbid'     => $this->releaseMbid,
            'listenedAt'      => $this->listenedAt,
            'source'          => $this->source,
            'resolutionState' => $this->resolutionState,
        ];
    }
}
