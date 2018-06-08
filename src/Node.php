<?php

namespace Duo\Scan;

/**
 * Class Node
 *
 * @package Duo\Scan
 */
class Node {

  /**
   * @var int
   */
  protected $nodeId;

  /**
   * @var string
   */
  protected $title;

  /**
   * @var string
   */
  protected $contentType;

  /**
   * @var int
   */
  protected $httpStatusCode;

  /**
   * @return int
   */
  public function getNodeId(): int {
    return $this->nodeId;
  }

  /**
   * @param int $nodeId
   */
  public function setNodeId(int $nodeId) {
    $this->nodeId = $nodeId;
  }

  /**
   * @return string
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * @param string $title
   */
  public function setTitle(string $title) {
    $this->title = $title;
  }

  /**
   * @return string
   */
  public function getContentType(): ?string {
    return $this->contentType;
  }

  /**
   * @param string $contentType
   */
  public function setContentType(string $contentType) {
    $this->contentType = $contentType;
  }

  /**
   * @return int
   */
  public function getHttpStatusCode(): int {
    return $this->httpStatusCode;
  }

  /**
   * @param int $httpStatusCode
   */
  public function setHttpStatusCode(int $httpStatusCode) {
    $this->httpStatusCode = $httpStatusCode;
  }

}
