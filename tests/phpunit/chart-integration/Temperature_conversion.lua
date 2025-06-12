local p = {}

local function celsius_to_fahrenheit(val)
	return val * 1.8 + 32
end

--
-- input data:
-- * tabular JSON strcuture with 1 label and 2 temp rows stored in C
--
-- arguments:
-- * units: "F" or "C"
--
function p.convert_temps(tab, args)
	if args.units == "C" then
		-- Stored data is in Celsius
		return tab
	elseif args.units == "F" then
		-- Have to convert if asked for Fahrenheit
		for _, row in ipairs(tab.data) do
			-- first column is month
			row[2] = celsius_to_fahrenheit(row[2])
			row[3] = celsius_to_fahrenheit(row[3])
		end
		return tab
	else
		error("Units must be either 'C' or 'F'")
	end
end

return p
