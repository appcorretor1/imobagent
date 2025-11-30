<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetText extends Model
{
    protected $table = 'assets_text';
    protected $primaryKey = 'asset_id';
    public $incrementing = false;

    protected $fillable = ['asset_id','content','lang','checksum'];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
