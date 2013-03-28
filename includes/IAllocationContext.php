<?php

/**
 * Provides the criteria needed to do allocation.
 */
interface IAllocationContext {
    function getCountry();
    function getProject();
    function getAnonymous();
    function getDevice();
    function getBucket();
}
