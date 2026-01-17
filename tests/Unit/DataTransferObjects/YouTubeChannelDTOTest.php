<?php

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\YouTubeChannelDTO;
use PHPUnit\Framework\TestCase;

class YouTubeChannelDTOTest extends TestCase
{
    public function test_can_instantiate_and_access_properties()
    {
        $dto = new YouTubeChannelDTO(
            channelId: 'UC1234567890',
            subscriberCount: 1000,
            videoCount: 50,
            subscriberCountHidden: false,
            title: 'Test Channel'
        );

        $this->assertEquals('UC1234567890', $dto->channelId);
        $this->assertEquals(1000, $dto->subscriberCount);
        $this->assertEquals('Test Channel', $dto->title);
    }

    public function test_properties_are_mutable()
    {
        $dto = new YouTubeChannelDTO(
            channelId: 'UC1234567890',
            subscriberCount: 1000,
            videoCount: 50,
            subscriberCountHidden: false
        );

        // Modify properties
        $dto->subscriberCount = 2000;
        $dto->videoCount = 60;
        $dto->title = 'Updated Title';

        $this->assertEquals(2000, $dto->subscriberCount);
        $this->assertEquals(60, $dto->videoCount);
        $this->assertEquals('Updated Title', $dto->title);
    }

    public function test_to_array_reflects_changes()
    {
        $dto = new YouTubeChannelDTO(
            channelId: 'UC1234567890',
            subscriberCount: 1000,
            videoCount: 50,
            subscriberCountHidden: false
        );

        $arrayOriginal = $dto->toArray();
        $this->assertEquals(1000, $arrayOriginal['subscriber_count']);

        // Update property
        $dto->subscriberCount = 5000;
        
        $arrayUpdated = $dto->toArray();
        $this->assertEquals(5000, $arrayUpdated['subscriber_count']);
    }
}
