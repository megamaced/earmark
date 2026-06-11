<?php

declare(strict_types=1);

namespace OCA\Earmark\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A per-user credential that authenticates an external scrobble client on
 * the inbound API. Only a hash of the token is stored (never the token
 * itself) — the plaintext is shown to the user once at creation. `createdAt`
 * and `lastUsedAt` are Unix timestamps (seconds).
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getTokenHash()
 * @method void setTokenHash(string $tokenHash)
 * @method string getTokenMd5()
 * @method void setTokenMd5(string $tokenMd5)
 * @method string|null getLabel()
 * @method void setLabel(?string $label)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int|null getLastUsedAt()
 * @method void setLastUsedAt(?int $lastUsedAt)
 */
class ScrobbleToken extends Entity implements \JsonSerializable
{
    protected string $userId = '';
    protected string $tokenHash = '';
    protected string $tokenMd5 = '';
    protected ?string $label = null;
    protected int $createdAt = 0;
    protected ?int $lastUsedAt = null;

    public function __construct()
    {
        $this->addType('createdAt', 'integer');
        $this->addType('lastUsedAt', 'integer');
    }

    /** Token metadata for the settings UI — never includes the hash. */
    public function jsonSerialize(): array
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'createdAt'  => $this->createdAt,
            'lastUsedAt' => $this->lastUsedAt,
        ];
    }
}
