<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A cached MusicBrainz resolution, keyed on the normalised track identity
 * ({@see \OCA\Earmark\Normalize::contentKey}). `matched` records whether a
 * confident match was found, so repeated unmatched lookups don't keep
 * hammering MusicBrainz. `resolvedAt` is a Unix timestamp (seconds).
 *
 * @method string getCacheKey()
 * @method void setCacheKey(string $cacheKey)
 * @method string|null getArtistMbid()
 * @method void setArtistMbid(?string $artistMbid)
 * @method string|null getRecordingMbid()
 * @method void setRecordingMbid(?string $recordingMbid)
 * @method string|null getReleaseMbid()
 * @method void setReleaseMbid(?string $releaseMbid)
 * @method bool getMatched()
 * @method void setMatched(bool $matched)
 * @method int getResolvedAt()
 * @method void setResolvedAt(int $resolvedAt)
 */
class MbCacheEntry extends Entity
{
    protected string $cacheKey = '';
    protected ?string $artistMbid = null;
    protected ?string $recordingMbid = null;
    protected ?string $releaseMbid = null;
    protected bool $matched = false;
    protected int $resolvedAt = 0;

    public function __construct()
    {
        $this->addType('matched', 'boolean');
        $this->addType('resolvedAt', 'integer');
    }
}
