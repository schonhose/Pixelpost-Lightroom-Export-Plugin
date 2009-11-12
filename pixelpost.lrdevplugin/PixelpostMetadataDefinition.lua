--[[----------------------------------------------------------------------------

CustomMetadataDefinition.lua
Sample custom metadata definition

--------------------------------------------------------------------------------

ADOBE SYSTEMS INCORPORATED
 Copyright 2008 Adobe Systems Incorporated
 All Rights Reserved.

NOTICE: Adobe permits you to use, modify, and distribute this file in accordance
with the terms of the Adobe license agreement accompanying it. If you have received
this file from a source other than Adobe, then your use, modification, or distribution
of it requires the prior written permission of Adobe.

------------------------------------------------------------------------------]]

return {

	metadataFieldsForPhotos = {
	
		{
			id = 'photo_id',
			-- This field will not be available in the metadata browser because
			-- it does not have a title field. You might use a field like this
			-- to store a photo ID from an external database or web service.
		},
	},
	
	schemaVersion = 1, -- must be a number, preferably a positive integer
	
	updateFromEarlierSchemaVersion = function( catalog, previousSchemaVersion )
		-- Note: This function is called from within a catalog:withPrivateWriteAccessDo
        -- block. You should not call any of the with___Do functions yourself.

        catalog:assertHasPrivateWriteAccess( "PixelpostMetadataDefinition.updateFromEarlierSchemaVersion" )

        if previousSchemaVersion == 1 then
			-- retrieve photos that have been used already with the custom metadata
			local photosToMigrate = catalog:findPhotosWithProperty(_G.pluginID, 'photo_id' )
				-- optional:  can add property version number here
				for i, photo in ipairs( photosToMigrate ) do
					local oldSiteId = photo:getPropertyForPlugin(_G.pluginID, 'photo_id' ) -- add property version here if used above
                	local newSiteId = "new:" .. oldSiteId -- replace this with whatever data transformation you need to do
					photo.catalog:withPrivateWriteAccessDo( function()
						photo:setPropertyForPlugin( _PLUGIN, 'photo_id', newSiteId )
					end )
				end
		elseif previousSchemaVersion == 2 then
			-- optional area to do further processing
            -- etc.
        end
    end,

}