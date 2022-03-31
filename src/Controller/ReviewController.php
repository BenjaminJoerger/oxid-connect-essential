<?php

namespace Makaira\OxidConnectEssential\Controller;

use Exception;
use Makaira\OxidConnectEssential\Service\ReviewService;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;

class ReviewController extends BaseController
{
    private ReviewService $reviewService;

    public function __construct()
    {
        parent::__construct();
        $this->reviewService = ContainerFactory::getInstance()->getContainer()->get(ReviewService::class);
    }

    public function getReviews()
    {
        ['id' => $productId, 'limit' => $limit, 'offset' => $offset] = $this->getRequestBody();

        $this->sendResponse($this->reviewService->getReviews($productId, $limit, $offset));
    }

    public function createReview()
    {
        $user = $this->checkAndGetActiveUser();
        ['product_id' => $productId, 'rating' => $rating, 'text' => $text] = $this->getRequestBody();

        try {
            $this->reviewService->createReview($productId, $rating, $text, $user);

            $this->sendResponse(['success' => 'true']);
        } catch (Exception $e) {
            $this->sendResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
